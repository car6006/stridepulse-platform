<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventEstimation;
use App\Models\EventFollower;
use App\Models\TelemetryAlert;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Support\Carbon;

class EventTelemetryAutomationService
{
    public function __construct(private WhatsAppDispatchService $dispatch) {}

    public function handleTelemetry(TrackingSession $trackingSession, TelemetryPoint $telemetryPoint): void
    {
        $trackingSession->loadMissing('raceEntry.event', 'athlete');
        $event = $trackingSession->raceEntry?->event;

        if (! $event) {
            return;
        }

        if ($telemetryPoint->gps_status && ! $this->alertExists($trackingSession, 'tracking_started')) {
            $this->triggerAlert($trackingSession, $event, 'tracking_started', 'tracking_started:'.$trackingSession->id, [
                'gps_status' => $telemetryPoint->gps_status,
                'recorded_at' => $telemetryPoint->recorded_at?->toIso8601String(),
            ], "{$trackingSession->athlete?->name} is live at {$event->name}. GPS is ready.");
        }

        if ($telemetryPoint->distance_m !== null) {
            $this->handleCheckpoint($trackingSession, $event, $telemetryPoint);
            $this->handleEstimation($trackingSession, $event, $telemetryPoint);
        }
    }

    private function handleCheckpoint(TrackingSession $trackingSession, Event $event, TelemetryPoint $telemetryPoint): void
    {
        $interval = (int) config('stridepulse.whatsapp.checkpoint_interval_m', 5000);

        if ($interval <= 0) {
            return;
        }

        $checkpoint = intdiv((int) $telemetryPoint->distance_m, $interval) * $interval;

        if ($checkpoint < $interval) {
            return;
        }

        $km = number_format($checkpoint / 1000, 1);

        $this->triggerAlert(
            $trackingSession,
            $event,
            'checkpoint_progress',
            "checkpoint_progress:{$trackingSession->id}:{$checkpoint}",
            [
                'distance_m' => $telemetryPoint->distance_m,
                'checkpoint_m' => $checkpoint,
            ],
            "{$trackingSession->athlete?->name} has passed {$km} km at {$event->name}.",
        );
    }

    private function handleEstimation(TrackingSession $trackingSession, Event $event, TelemetryPoint $telemetryPoint): void
    {
        $eventDistance = data_get($event->metadata, 'distance_m');
        $elapsed = $telemetryPoint->elapsed_time_seconds ?? $telemetryPoint->elapsed_seconds;

        if (! is_numeric($eventDistance) || ! is_numeric($elapsed) || (float) $telemetryPoint->distance_m <= 0 || (int) $elapsed <= 0) {
            return;
        }

        $paceSecPerM = ((int) $elapsed) / (float) $telemetryPoint->distance_m;
        $remaining = max(0, (float) $eventDistance - (float) $telemetryPoint->distance_m);
        $estimatedFinish = ($telemetryPoint->recorded_at ?? now())->copy()->addSeconds((int) round($remaining * $paceSecPerM));

        $latest = EventEstimation::query()
            ->where('tracking_session_id', $trackingSession->id)
            ->latest()
            ->first();

        EventEstimation::query()->create([
            'tracking_session_id' => $trackingSession->id,
            'event_id' => $event->id,
            'distance_m' => $telemetryPoint->distance_m,
            'remaining_distance_m' => $remaining,
            'average_pace_sec_per_km' => (int) round($paceSecPerM * 1000),
            'estimated_finish_at' => $estimatedFinish,
            'payload' => [
                'recorded_at' => $telemetryPoint->recorded_at?->toIso8601String(),
            ],
        ]);

        $threshold = (int) config('stridepulse.whatsapp.estimation_change_threshold_minutes', 15);

        if ($latest?->estimated_finish_at instanceof Carbon && abs($latest->estimated_finish_at->diffInMinutes($estimatedFinish, false)) < $threshold) {
            return;
        }

        $this->triggerAlert(
            $trackingSession,
            $event,
            'estimated_finish',
            'estimated_finish:'.$trackingSession->id.':'.$estimatedFinish->format('YmdHi'),
            ['estimated_finish_at' => $estimatedFinish->toIso8601String()],
            "{$trackingSession->athlete?->name}'s estimated finish is {$estimatedFinish->format('H:i')}.",
        );
    }

    private function triggerAlert(TrackingSession $trackingSession, Event $event, string $type, string $dedupeKey, array $payload, string $message): void
    {
        $alert = TelemetryAlert::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'tracking_session_id' => $trackingSession->id,
                'event_id' => $event->id,
                'alert_type' => $type,
                'payload' => $payload,
                'triggered_at' => now(),
            ],
        );

        if (! $alert->wasRecentlyCreated) {
            return;
        }

        EventFollower::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->each(function (EventFollower $follower) use ($trackingSession, $event, $type, $alert, $message) {
                $this->dispatch->sendText(
                    $follower->phone_number,
                    $message,
                    "event_update:{$type}:{$alert->id}:{$follower->id}",
                    trackingSession: $trackingSession,
                    event: $event,
                );
            });
    }

    private function alertExists(TrackingSession $trackingSession, string $type): bool
    {
        return TelemetryAlert::query()
            ->where('tracking_session_id', $trackingSession->id)
            ->where('alert_type', $type)
            ->exists();
    }
}
