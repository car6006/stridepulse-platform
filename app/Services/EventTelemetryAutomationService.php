<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventEstimation;
use App\Models\EventFollower;
use App\Models\SupporterConsent;
use App\Models\TelemetryAlert;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Carbon\CarbonInterface;
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

        $eventDistance = data_get($event->metadata, 'distance_m');
        $remainingDistance = is_numeric($eventDistance)
            ? max(0, (float) $eventDistance - (float) $telemetryPoint->distance_m)
            : null;
        $averagePace = $this->averagePaceSecondsPerKm($telemetryPoint);
        $estimatedFinish = $this->estimatedFinish($telemetryPoint, $remainingDistance, $averagePace);
        $liveTrackingUrl = route('live.session', $trackingSession->session_token);

        $this->triggerAlert(
            $trackingSession,
            $event,
            'checkpoint_progress',
            "checkpoint_progress:{$trackingSession->id}:{$checkpoint}",
            [
                'distance_m' => $telemetryPoint->distance_m,
                'checkpoint_m' => $checkpoint,
                'completed_distance_m' => $checkpoint,
                'remaining_distance_m' => $remainingDistance,
                'average_pace_sec_per_km' => $averagePace,
                'estimated_finish_at' => $estimatedFinish?->toIso8601String(),
                'live_tracking_url' => $liveTrackingUrl,
            ],
            $this->checkpointMessage(
                $trackingSession,
                $event,
                $checkpoint,
                $averagePace,
                $remainingDistance,
                $estimatedFinish,
                $liveTrackingUrl,
            ),
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
            ->whereNotNull('opted_in_at')
            ->whereNull('unsubscribed_at')
            ->where(function ($query) {
                $query->whereNull('supporter_consent_id')
                    ->orWhereHas('consent', fn ($consentQuery) => $consentQuery->where('status', SupporterConsent::STATUS_OPTED_IN));
            })
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

    private function checkpointMessage(
        TrackingSession $trackingSession,
        Event $event,
        int $checkpoint,
        ?int $averagePace,
        ?float $remainingDistance,
        ?CarbonInterface $estimatedFinish,
        string $liveTrackingUrl,
    ): string {
        return implode("\n", [
            "{$trackingSession->athlete?->name} checkpoint update for {$event->name}",
            'Completed: '.$this->formatDistance($checkpoint),
            'Average pace: '.$this->formatPace($averagePace),
            'Remaining: '.$this->formatDistance($remainingDistance),
            'Estimated finish: '.($estimatedFinish?->format('H:i') ?? 'calculating'),
            'Live tracking: '.$liveTrackingUrl,
        ]);
    }

    private function averagePaceSecondsPerKm(TelemetryPoint $telemetryPoint): ?int
    {
        if (is_numeric($telemetryPoint->average_pace_sec_per_km) && (int) $telemetryPoint->average_pace_sec_per_km > 0) {
            return (int) $telemetryPoint->average_pace_sec_per_km;
        }

        $elapsed = $telemetryPoint->elapsed_time_seconds ?? $telemetryPoint->elapsed_seconds;

        if (! is_numeric($elapsed) || ! is_numeric($telemetryPoint->distance_m) || (int) $elapsed <= 0 || (float) $telemetryPoint->distance_m <= 0) {
            return null;
        }

        return (int) round(((int) $elapsed) / ((float) $telemetryPoint->distance_m / 1000));
    }

    private function estimatedFinish(TelemetryPoint $telemetryPoint, ?float $remainingDistance, ?int $averagePace): ?CarbonInterface
    {
        if ($remainingDistance === null || $averagePace === null) {
            return null;
        }

        return ($telemetryPoint->recorded_at ?? now())->copy()->addSeconds((int) round(($remainingDistance / 1000) * $averagePace));
    }

    private function formatDistance(float|int|null $distanceM): string
    {
        if ($distanceM === null) {
            return 'unknown';
        }

        return number_format(((float) $distanceM) / 1000, 1).' km';
    }

    private function formatPace(?int $secondsPerKm): string
    {
        if ($secondsPerKm === null || $secondsPerKm <= 0) {
            return 'calculating';
        }

        return sprintf('%d:%02d/km', intdiv($secondsPerKm, 60), $secondsPerKm % 60);
    }
}
