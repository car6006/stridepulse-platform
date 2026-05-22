<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use App\Services\EventTelemetryAutomationService;
use App\Services\GarminDeviceDiscoveryService;
use App\Services\TrackingSessionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GarminTelemetryController extends Controller
{
    public function store(
        Request $request,
        TrackingSessionLifecycleService $lifecycle,
        GarminDeviceDiscoveryService $deviceDiscovery,
        EventTelemetryAutomationService $automation,
    ): JsonResponse {
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
            'session_token' => ['nullable', 'required_without:device_uuid', 'string', 'max:255'],
            'device_uuid' => ['nullable', 'required_with:device_secret', 'string', 'max:255'],
            'device_secret' => ['nullable', 'required_with:device_uuid', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
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
            'ascent_m' => ['nullable', 'numeric'],
            'descent_m' => ['nullable', 'numeric'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'lap_number' => ['nullable', 'integer', 'min:0'],
            'gps_status' => ['nullable', 'string', 'max:50'],
            'battery_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'activity_state' => ['nullable', 'string', 'in:active,paused,stopped,completed,discarded,abandoned'],
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
        $device = $deviceDiscovery->resolve($validated);

        if ($device === false) {
            return response()->json([
                'ok' => false,
                'status' => 'device_forbidden',
                'message' => 'Device credentials are invalid',
            ], 403);
        }

        $trackingSession = null;

        if (! empty($validated['session_token'])) {
            $trackingSession = TrackingSession::query()
                ->where('session_token', $validated['session_token'])
                ->when(in_array($activityState, ['active', 'paused'], true), fn ($query) => $query->whereNull('ended_at'))
                ->first();
        }

        if (! $trackingSession && empty($validated['session_token']) && $device instanceof Device && $device->status === 'active') {
            $trackingSession = TrackingSession::query()
                ->where('device_id', $device->id)
                ->whereNull('ended_at')
                ->whereIn('status', ['active', 'paused', 'stationary'])
                ->latest('started_at')
                ->latest('id')
                ->first();
        }

        if (! $trackingSession) {
            if ($device instanceof Device) {
                $device->forceFill([
                    'last_seen_at' => now(),
                    'status' => $device->isClaimedForLifecycle() ? Device::STATUS_READY : $device->status,
                ])->save();

                Log::info('Garmin device heartbeat without valid tracking session', [
                    'device_id' => $device->id,
                    'device_uuid' => $device->device_uuid,
                    'session_token' => $validated['session_token'] ?? null,
                ]);

                return response()->json([
                    'ok' => true,
                    'status' => 'device_seen',
                    'message' => 'Device heartbeat received',
                ]);
            }

            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'message' => 'Tracking session not found',
            ], 404);
        }

        if ($trackingSession->device_id !== null && ! ($device instanceof Device)) {
            return response()->json([
                'ok' => false,
                'status' => 'device_required',
                'message' => 'Tracking session requires device identity',
            ], 403);
        }

        if ($device instanceof Device && $trackingSession->device_id !== null && (int) $trackingSession->device_id !== (int) $device->id) {
            return response()->json([
                'ok' => false,
                'status' => 'device_session_mismatch',
                'message' => 'Tracking session is not assigned to this device',
            ], 403);
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

                if ($device instanceof Device) {
                    $device->forceFill([
                        'last_seen_at' => now(),
                        'last_telemetry_at' => now(),
                        'status' => $device->isClaimedForLifecycle() ? Device::STATUS_LIVE : $device->status,
                    ])->save();
                }

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

        $telemetryPoint = TelemetryPoint::query()->create([
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

        $trackingSession->forceFill([
            'last_seen_at' => now(),
            'last_direct_telemetry_at' => now(),
        ])->save();
        if ($device instanceof Device) {
            $device->forceFill([
                'last_seen_at' => now(),
                'last_telemetry_at' => now(),
                'status' => $device->isClaimedForLifecycle() ? Device::STATUS_LIVE : $device->status,
            ])->save();
        }

        $lifecycle->evaluateAfterTelemetry($trackingSession, $telemetryPoint, $activityState);
        $automation->handleTelemetry($trackingSession, $telemetryPoint);

        return $this->receivedResponse();
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
