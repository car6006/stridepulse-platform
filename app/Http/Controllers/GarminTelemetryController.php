<?php

namespace App\Http\Controllers;

use App\Models\AthleteActivity;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GarminTelemetryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $decodedPayload = json_decode($rawBody, true);

        Log::debug('Garmin telemetry request received', [
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
            'raw_body' => $rawBody,
            'input' => $request->all(),
            'json_payload' => json_last_error() === JSON_ERROR_NONE ? $decodedPayload : null,
        ]);

        $validator = Validator::make($request->all(), [
            'session_token' => ['required', 'string', 'max:255'],
            'ingestion_id' => ['nullable', 'string', 'max:255'],
            'recorded_at' => ['required', 'date'],
            'elapsed_seconds' => ['nullable', 'integer', 'min:0'],
            'elapsed_time_seconds' => ['nullable', 'integer', 'min:0'],
            'distance_m' => ['nullable', 'numeric', 'min:0'],
            'pace_sec_per_km' => ['nullable', 'integer', 'min:0'],
            'average_pace_sec_per_km' => ['nullable', 'integer', 'min:0'],
            'current_speed_mps' => ['nullable', 'numeric', 'min:0'],
            'heart_rate_bpm' => ['nullable', 'integer', 'min:0', 'max:255'],
            'avg_heart_rate_bpm' => ['nullable', 'integer', 'min:0', 'max:255'],
            'cadence' => ['nullable', 'integer', 'min:0', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'altitude_m' => ['nullable', 'numeric'],
            'heading_degrees' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'ascent_m' => ['nullable', 'numeric', 'min:0'],
            'descent_m' => ['nullable', 'numeric', 'min:0'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'lap_number' => ['nullable', 'integer', 'min:0'],
            'gps_status' => ['nullable', 'string', 'max:50'],
            'battery_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'activity_state' => ['nullable', 'string', 'in:active,stopped,completed,abandoned'],
            'raw_payload' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            Log::debug('Garmin telemetry validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
            ]);
        }

        $validated = $validator->validate();
        $activityState = $validated['activity_state'] ?? 'active';

        $trackingSession = TrackingSession::query()
            ->where('session_token', $validated['session_token'])
            ->when($activityState === 'active', fn ($query) => $query->whereNull('ended_at'))
            ->first();

        if (! $trackingSession) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'message' => 'Tracking session not found',
            ], 404);
        }

        if (! empty($validated['ingestion_id'])) {
            $existingPoint = TelemetryPoint::query()
                ->where('tracking_session_id', $trackingSession->id)
                ->where('ingestion_id', $validated['ingestion_id'])
                ->first();

            if ($existingPoint) {
                $trackingSession->forceFill([
                    'last_seen_at' => now(),
                    'last_direct_telemetry_at' => now(),
                ])->save();

                return $this->receivedResponse();
            }
        }

        // Normalize/cap ascent/descent values (ignore or nullify if unrealistic)
        $ascent = $validated['ascent_m'] ?? null;
        $descent = $validated['descent_m'] ?? null;
        $ascent = (is_numeric($ascent) && $ascent >= 0 && $ascent < 10000) ? $ascent : null;
        $descent = (is_numeric($descent) && $descent >= 0 && $descent < 10000) ? $descent : null;

        // Normalize altitude, heading, average pace, elapsed time, device model (optional fields)
        $altitude = $validated['altitude_m'] ?? null;
        $altitude = (is_numeric($altitude) && abs($altitude) < 10000) ? $altitude : null;

        $heading = $validated['heading_degrees'] ?? null;
        $heading = (is_numeric($heading) && $heading >= 0 && $heading <= 360) ? $heading : null;

        $averagePace = $validated['average_pace_sec_per_km'] ?? null;
        $averagePace = (is_numeric($averagePace) && $averagePace > 0 && $averagePace < 3600) ? $averagePace : null;

        $elapsedTime = $validated['elapsed_time_seconds'] ?? null;
        $elapsedTime = (is_numeric($elapsedTime) && $elapsedTime >= 0 && $elapsedTime < 86400) ? $elapsedTime : null;

        $deviceModel = $validated['device_model'] ?? null;
        $deviceModel = (is_string($deviceModel) && strlen($deviceModel) <= 255) ? $deviceModel : null;

        TelemetryPoint::query()->create([
            'tracking_session_id' => $trackingSession->id,
            'ingestion_id' => $validated['ingestion_id'] ?? null,
            'recorded_at' => Carbon::parse($validated['recorded_at']),
            'elapsed_seconds' => $validated['elapsed_seconds'] ?? null,
            'elapsed_time_seconds' => $elapsedTime,
            'distance_m' => $validated['distance_m'] ?? null,
            'pace_sec_per_km' => $validated['pace_sec_per_km'] ?? null,
            'average_pace_sec_per_km' => $averagePace,
            'current_speed_mps' => $validated['current_speed_mps'] ?? null,
            'heart_rate_bpm' => $validated['heart_rate_bpm'] ?? null,
            'avg_heart_rate_bpm' => $validated['avg_heart_rate_bpm'] ?? null,
            'cadence' => $validated['cadence'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'altitude_m' => $altitude,
            'heading_degrees' => $heading,
            'ascent_m' => $ascent,
            'descent_m' => $descent,
            'calories' => $validated['calories'] ?? null,
            'lap_number' => $validated['lap_number'] ?? null,
            'gps_status' => $validated['gps_status'] ?? null,
            'battery_percent' => $validated['battery_percent'] ?? null,
            'device_model' => $deviceModel,
            'raw_payload' => $request->all(),
            'metadata' => [],
        ]);

        $recordedAt = Carbon::parse($validated['recorded_at']);

        $sessionUpdates = [
            'last_seen_at' => now(),
            'last_direct_telemetry_at' => now(),
        ];

        if (in_array($activityState, ['stopped', 'completed', 'abandoned'], true)) {
            $sessionUpdates['status'] = $activityState;
            $sessionUpdates['ended_at'] = $trackingSession->ended_at ?? $recordedAt;
        }

        $trackingSession->forceFill($sessionUpdates)->save();

        if ($activityState === 'completed') {
            $this->createOrUpdateAthleteActivity($trackingSession->fresh(['athlete', 'sport']));
        }

        return $this->receivedResponse();
    }

    private function createOrUpdateAthleteActivity(TrackingSession $trackingSession): AthleteActivity
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
                'status' => 'completed',
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

    private function receivedResponse(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => 'received',
            'message' => 'Pulse received',
        ]);
    }
}
