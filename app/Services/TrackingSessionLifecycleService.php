<?php

namespace App\Services;

use App\Models\AthleteActivity;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Support\Carbon;

class TrackingSessionLifecycleService
{
    public const STATE_ACTIVE = 'active';
    public const STATE_PAUSED = 'paused';
    public const STATE_STATIONARY = 'stationary';
    public const STATE_STOPPED = 'stopped';
    public const STATE_COMPLETED = 'completed';
    public const STATE_DISCARDED = 'discarded';
    public const STATE_ABANDONED = 'abandoned';

    public const MESSAGE_PROGRESS = 'progress';
    public const MESSAGE_STATIONARY = 'stationary';
    public const MESSAGE_STOPPED = 'stopped';
    public const MESSAGE_COMPLETED = 'completed';
    public const MESSAGE_ABANDONED = 'abandoned';
    public const MESSAGE_EMERGENCY = 'emergency';

    public function evaluateAfterTelemetry(
        TrackingSession $trackingSession,
        TelemetryPoint $latestTelemetry,
        ?string $activityState = null,
    ): TrackingSession {
        $trackingSession->refresh();
        $activityState = $activityState ?: self::STATE_ACTIVE;
        $recordedAt = $latestTelemetry->recorded_at ?? now();

        if ($activityState === self::STATE_PAUSED) {
            return $this->transition($trackingSession, self::STATE_PAUSED, [
                'last_seen_at' => now(),
                'last_direct_telemetry_at' => now(),
            ]);
        }

        if ($activityState === self::STATE_STOPPED) {
            return $this->transition($trackingSession, self::STATE_STOPPED, [
                'last_seen_at' => now(),
                'last_direct_telemetry_at' => now(),
                'ended_at' => $trackingSession->ended_at ?? $recordedAt,
                'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
            ]);
        }

        if ($activityState === self::STATE_COMPLETED) {
            $trackingSession = $this->transition($trackingSession, self::STATE_COMPLETED, [
                'last_seen_at' => now(),
                'last_direct_telemetry_at' => now(),
                'ended_at' => $trackingSession->ended_at ?? $recordedAt,
                'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
            ]);

            $this->createOrUpdateAthleteActivity($trackingSession->fresh(['athlete', 'sport']));

            return $trackingSession->fresh();
        }

        if ($activityState === self::STATE_DISCARDED) {
            return $this->transition($trackingSession, self::STATE_DISCARDED, [
                'last_seen_at' => now(),
                'last_direct_telemetry_at' => now(),
                'ended_at' => $trackingSession->ended_at ?? $recordedAt,
                'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
            ]);
        }

        if ($activityState === self::STATE_ABANDONED) {
            return $this->transition($trackingSession, self::STATE_ABANDONED, [
                'last_seen_at' => now(),
                'last_direct_telemetry_at' => now(),
                'ended_at' => $trackingSession->ended_at ?? $recordedAt,
                'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
            ]);
        }

        if ($this->hasTerminalState($trackingSession)) {
            return $trackingSession;
        }

        $comparisonPoint = $this->comparisonPointFor($trackingSession, $latestTelemetry);
        $movementDetected = $this->movementDetected($latestTelemetry, $comparisonPoint);

        $updates = [
            'last_seen_at' => now(),
            'last_direct_telemetry_at' => now(),
        ];

        if ($movementDetected) {
            $updates['last_movement_at'] = $recordedAt;

            return $this->transition($trackingSession, self::STATE_ACTIVE, $updates);
        }

        if ($this->stationaryWindowReached($latestTelemetry, $comparisonPoint)) {
            return $this->transition($trackingSession, self::STATE_STATIONARY, $updates);
        }

        return $this->transition($trackingSession, self::STATE_ACTIVE, $updates);
    }

    public function completeManually(TrackingSession $trackingSession, ?Carbon $endedAt = null): TrackingSession
    {
        $trackingSession = $this->transition($trackingSession, self::STATE_COMPLETED, [
            'ended_at' => $trackingSession->ended_at ?? ($endedAt ?? now()),
            'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
        ]);

        $this->createOrUpdateAthleteActivity($trackingSession->fresh(['athlete', 'sport']));

        return $trackingSession->fresh();
    }

    public function discardManually(TrackingSession $trackingSession, ?Carbon $endedAt = null): TrackingSession
    {
        return $this->transition($trackingSession, self::STATE_DISCARDED, [
            'ended_at' => $trackingSession->ended_at ?? ($endedAt ?? now()),
            'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? now(),
        ]);
    }

    public function evaluateAbandoned(TrackingSession $trackingSession, ?Carbon $now = null): TrackingSession
    {
        $now ??= now();

        if ($this->hasTerminalState($trackingSession)) {
            return $trackingSession;
        }

        $lastTelemetryAt = $trackingSession->last_direct_telemetry_at
            ?? $trackingSession->last_seen_at
            ?? $trackingSession->started_at;

        if ($lastTelemetryAt === null) {
            return $trackingSession;
        }

        if ($lastTelemetryAt->lte($now->copy()->subSeconds($this->configInt('abandon_after_seconds')))) {
            return $this->transition($trackingSession, self::STATE_ABANDONED, [
                'ended_at' => $trackingSession->ended_at ?? $now,
                'notification_suppressed_at' => $trackingSession->notification_suppressed_at ?? $now,
            ]);
        }

        return $trackingSession;
    }

    public function canSendSupporterUpdate(
        TrackingSession $trackingSession,
        string $messageType,
        bool $markAllowed = true,
        ?Carbon $now = null,
    ): bool {
        $now ??= now();
        $trackingSession->refresh();

        if ($messageType === self::MESSAGE_EMERGENCY) {
            return true;
        }

        if ($messageType === self::MESSAGE_PROGRESS) {
            return $trackingSession->status === self::STATE_ACTIVE;
        }

        $state = $trackingSession->notification_state ?? [];

        if ($messageType === self::MESSAGE_STATIONARY) {
            if ($trackingSession->status !== self::STATE_STATIONARY) {
                return false;
            }

            $lastSentAt = isset($state['stationary']['last_sent_at'])
                ? Carbon::parse($state['stationary']['last_sent_at'])
                : null;

            if ($lastSentAt && $lastSentAt->gt($now->copy()->subSeconds($this->configInt('notification_cooldown_seconds')))) {
                return false;
            }

            if ($markAllowed) {
                $state['stationary']['last_sent_at'] = $now->toIso8601String();
                $trackingSession->forceFill(['notification_state' => $state])->save();
            }

            return true;
        }

        $onceByState = [
            self::MESSAGE_STOPPED => self::STATE_STOPPED,
            self::MESSAGE_COMPLETED => self::STATE_COMPLETED,
            self::MESSAGE_ABANDONED => self::STATE_ABANDONED,
        ];

        if (! isset($onceByState[$messageType]) || $trackingSession->status !== $onceByState[$messageType]) {
            return false;
        }

        if (! empty($state[$messageType]['sent_at'])) {
            return false;
        }

        if ($markAllowed) {
            $state[$messageType]['sent_at'] = $now->toIso8601String();
            $trackingSession->forceFill(['notification_state' => $state])->save();
        }

        return true;
    }

    public function createOrUpdateAthleteActivity(TrackingSession $trackingSession): AthleteActivity
    {
        $points = $trackingSession->telemetryPoints()
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $latestPoint = $points->last();
        $firstLocation = $points->first(fn (TelemetryPoint $point) => $point->latitude !== null && $point->longitude !== null);
        $lastLocation = $points->reverse()->first(fn (TelemetryPoint $point) => $point->latitude !== null && $point->longitude !== null);
        $distance = $this->lastNonNull($points, 'distance_m');
        $duration = $this->lastNonNull($points, 'elapsed_time_seconds') ?? $this->lastNonNull($points, 'elapsed_seconds');
        $averageHeartRate = $this->averageInteger($points, 'heart_rate_bpm') ?? $this->lastNonNull($points, 'avg_heart_rate_bpm');
        $maxHeartRate = $points->pluck('heart_rate_bpm')->filter(fn ($value) => $value !== null)->max();
        $averagePace = $this->averagePace($distance, $duration, $points);

        return AthleteActivity::query()->updateOrCreate(
            ['tracking_session_id' => $trackingSession->id],
            [
                'uuid' => $trackingSession->athleteActivity?->uuid ?? (string) str()->uuid(),
                'athlete_id' => $trackingSession->athlete_id,
                'sport_id' => $trackingSession->sport_id,
                'source' => $trackingSession->telemetry_source ?? 'connect_iq',
                'status' => self::STATE_COMPLETED,
                'started_at' => $trackingSession->started_at,
                'ended_at' => $trackingSession->ended_at,
                'duration_seconds' => $duration !== null ? (int) $duration : null,
                'distance_m' => $distance,
                'average_pace_sec_per_km' => $averagePace,
                'average_heart_rate_bpm' => $averageHeartRate !== null ? (int) round($averageHeartRate) : null,
                'max_heart_rate_bpm' => $maxHeartRate !== null ? (int) $maxHeartRate : null,
                'calories' => $this->lastNonNull($points, 'calories'),
                'ascent_m' => $this->lastNonNull($points, 'ascent_m'),
                'descent_m' => $this->lastNonNull($points, 'descent_m'),
                'start_latitude' => $firstLocation?->latitude,
                'start_longitude' => $firstLocation?->longitude,
                'end_latitude' => $lastLocation?->latitude,
                'end_longitude' => $lastLocation?->longitude,
                'summary_payload' => [
                    'telemetry_points_count' => $points->count(),
                    'latest_telemetry_point_id' => $latestPoint?->id,
                    'latest_recorded_at' => $latestPoint?->recorded_at?->toIso8601String(),
                    'final_distance_m' => $distance,
                    'duration_seconds' => $duration,
                    'average_heart_rate_bpm' => $averageHeartRate,
                    'max_heart_rate_bpm' => $maxHeartRate,
                    'average_pace_sec_per_km' => $averagePace,
                ],
            ],
        );
    }

    private function transition(TrackingSession $trackingSession, string $state, array $updates = []): TrackingSession
    {
        if ($trackingSession->status !== $state) {
            $updates['status'] = $state;
            $updates['last_status_changed_at'] = now();
        }

        $trackingSession->forceFill($updates)->save();

        return $trackingSession->fresh();
    }

    private function comparisonPointFor(TrackingSession $trackingSession, TelemetryPoint $latestTelemetry): ?TelemetryPoint
    {
        $recordedAt = $latestTelemetry->recorded_at;

        if (! $recordedAt) {
            return null;
        }

        $windowPoint = $trackingSession->telemetryPoints()
            ->whereKeyNot($latestTelemetry->id)
            ->where('recorded_at', '<=', $recordedAt->copy()->subSeconds($this->configInt('stationary_after_seconds')))
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return $windowPoint ?: $trackingSession->telemetryPoints()
            ->whereKeyNot($latestTelemetry->id)
            ->latest('recorded_at')
            ->latest('id')
            ->first();
    }

    private function movementDetected(TelemetryPoint $latestTelemetry, ?TelemetryPoint $comparisonPoint): bool
    {
        if (! $comparisonPoint) {
            return true;
        }

        $distanceDelta = $this->distanceDelta($latestTelemetry, $comparisonPoint);
        $gpsDelta = $this->gpsDelta($latestTelemetry, $comparisonPoint);

        if ($distanceDelta === null && $gpsDelta === null) {
            return true;
        }

        return ($distanceDelta !== null && $distanceDelta >= $this->configFloat('stationary_distance_threshold_m'))
            || ($gpsDelta !== null && $gpsDelta >= $this->configFloat('stationary_gps_threshold_m'));
    }

    private function stationaryWindowReached(TelemetryPoint $latestTelemetry, ?TelemetryPoint $comparisonPoint): bool
    {
        if (! $comparisonPoint || ! $latestTelemetry->recorded_at || ! $comparisonPoint->recorded_at) {
            return false;
        }

        return $comparisonPoint->recorded_at->lte(
            $latestTelemetry->recorded_at->copy()->subSeconds($this->configInt('stationary_after_seconds')),
        );
    }

    private function distanceDelta(TelemetryPoint $latestTelemetry, TelemetryPoint $comparisonPoint): ?float
    {
        if ($latestTelemetry->distance_m === null || $comparisonPoint->distance_m === null) {
            return null;
        }

        return abs((float) $latestTelemetry->distance_m - (float) $comparisonPoint->distance_m);
    }

    private function gpsDelta(TelemetryPoint $latestTelemetry, TelemetryPoint $comparisonPoint): ?float
    {
        if (
            $latestTelemetry->latitude === null ||
            $latestTelemetry->longitude === null ||
            $comparisonPoint->latitude === null ||
            $comparisonPoint->longitude === null
        ) {
            return null;
        }

        $earthRadiusM = 6371000;
        $latFrom = deg2rad((float) $comparisonPoint->latitude);
        $latTo = deg2rad((float) $latestTelemetry->latitude);
        $latDelta = deg2rad((float) $latestTelemetry->latitude - (float) $comparisonPoint->latitude);
        $lngDelta = deg2rad((float) $latestTelemetry->longitude - (float) $comparisonPoint->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * (sin($lngDelta / 2) ** 2);

        return $earthRadiusM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function hasTerminalState(TrackingSession $trackingSession): bool
    {
        return in_array($trackingSession->status, [
            self::STATE_STOPPED,
            self::STATE_COMPLETED,
            self::STATE_DISCARDED,
            self::STATE_ABANDONED,
            'ended',
        ], true);
    }

    private function lastNonNull($points, string $field): mixed
    {
        $point = $points->reverse()->first(fn (TelemetryPoint $point) => $point->{$field} !== null);

        return $point?->{$field};
    }

    private function averageInteger($points, string $field): ?int
    {
        $values = $points->pluck($field)->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->avg());
    }

    private function averagePace($distance, $duration, $points): ?int
    {
        if (is_numeric($distance) && is_numeric($duration) && (float) $distance > 0 && (int) $duration > 0) {
            return (int) round(((int) $duration) / (((float) $distance) / 1000));
        }

        return $this->lastNonNull($points, 'average_pace_sec_per_km')
            ?? $this->lastNonNull($points, 'pace_sec_per_km');
    }

    private function configInt(string $key): int
    {
        return (int) config("stridepulse.tracking.{$key}");
    }

    private function configFloat(string $key): float
    {
        return (float) config("stridepulse.tracking.{$key}");
    }
}
