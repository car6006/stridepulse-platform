<?php

namespace App\Http\Controllers;

use App\Models\Sport;
use App\Models\TrackingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrackingSessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'race_entry_id' => ['nullable', 'integer', 'exists:race_entries,id'],
        ]);

        $sport = Sport::query()->findOrFail($validated['sport_id']);
        $sessionToken = Str::random(48);

        $trackingSession = TrackingSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'session_token' => $sessionToken,
            'athlete_id' => $validated['athlete_id'],
            'sport_id' => $validated['sport_id'],
            'race_entry_id' => $validated['race_entry_id'] ?? null,
            'device_source' => 'garmin_connect_iq',
            'activity_type' => Str::slug($sport->name, '_'),
            'status' => 'active',
            'started_at' => now(),
            'telemetry_source' => 'connect_iq',
            'metadata' => [],
        ]);

        return response()->json([
            'ok' => true,
            'status' => 'active',
            'session_token' => $trackingSession->session_token,
            'tracking_session' => $trackingSession->uuid,
            'garmin_setup_token' => $trackingSession->session_token,
            'livetrack_inbound_alias' => $trackingSession->session_token,
            'telemetry_endpoint_url' => url('/api/garmin/telemetry'),
        ], 201);
    }

    public function status(string $sessionToken): JsonResponse
    {
        $trackingSession = TrackingSession::query()
            ->where('session_token', $sessionToken)
            ->first();

        if (! $trackingSession) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'message' => 'Tracking session not found',
            ], 404);
        }

        $latestTelemetryPoint = $trackingSession->telemetryPoints()
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return response()->json([
            'ok' => true,
            'status' => $trackingSession->status,
            'session_token' => $trackingSession->session_token,
            'tracking_session' => $trackingSession->uuid,
            'last_seen_at' => $trackingSession->last_seen_at,
            'last_direct_telemetry_at' => $trackingSession->last_direct_telemetry_at,
            'livetrack_url' => $trackingSession->livetrack_url,
            'latest_telemetry' => $latestTelemetryPoint ? [
                'recorded_at' => $latestTelemetryPoint->recorded_at,
                'elapsed_seconds' => $latestTelemetryPoint->elapsed_seconds,
                'distance_m' => $latestTelemetryPoint->distance_m,
                'pace_sec_per_km' => $latestTelemetryPoint->pace_sec_per_km,
                'heart_rate_bpm' => $latestTelemetryPoint->heart_rate_bpm,
                'avg_heart_rate_bpm' => $latestTelemetryPoint->avg_heart_rate_bpm,
                'cadence' => $latestTelemetryPoint->cadence,
                'latitude' => $latestTelemetryPoint->latitude,
                'longitude' => $latestTelemetryPoint->longitude,
                'gps_status' => $latestTelemetryPoint->gps_status,
                'battery_percent' => $latestTelemetryPoint->battery_percent,
                'device_model' => $latestTelemetryPoint->device_model,
            ] : null,
        ]);
    }
}
