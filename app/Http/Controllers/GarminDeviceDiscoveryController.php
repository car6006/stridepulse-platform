<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TrackingSession;
use App\Services\GarminDeviceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GarminDeviceDiscoveryController extends Controller
{
    public function store(Request $request, GarminDeviceDiscoveryService $deviceDiscovery): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:255'],
            'device_secret' => ['required', 'string', 'max:255'],
            'device_model' => ['required', 'string', 'max:255'],
            'app_version' => ['required', 'string', 'max:50'],
            'battery_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = $deviceDiscovery->resolve($validated);

        if ($device === false) {
            return response()->json([
                'ok' => false,
                'status' => 'device_forbidden',
                'message' => 'Device credentials are invalid',
            ], 403);
        }

        /** @var Device $device */
        $device->load('athlete');

        $resolution = $device->isClaimedForLifecycle()
            ? $this->resolveActiveSession($device)
            : [
                'session' => null,
                'sessions' => collect(),
                'matched_by' => 'none',
            ];

        /** @var Collection $matchingSessions */
        $matchingSessions = $resolution['sessions'];
        $trackingSession = $resolution['session'];
        $tokenReturned = $trackingSession instanceof TrackingSession && filled($trackingSession->session_token);

        $debugSessionIds = $matchingSessions->pluck('id')->values()->all();

        Log::info('garmin.discovery', [
            'device_uuid_prefix' => $this->deviceUuidPrefix($validated['device_uuid']),
            'device_id' => $device->id,
            'device_status' => $device->status,
            'athlete_id' => $device->athlete_id,
            'matching_session_count' => $matchingSessions->count(),
            'matching_session_ids' => $debugSessionIds,
            'matched_by' => $resolution['matched_by'],
            'token_returned' => $tokenReturned,
            'session_status' => $trackingSession?->status,
        ]);

        if ($device->status !== Device::STATUS_ARCHIVED) {
            $device->forceFill([
                'status' => $tokenReturned ? Device::STATUS_LIVE : ($device->isClaimedForLifecycle() ? Device::STATUS_READY : Device::STATUS_UNCLAIMED),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'status' => $device->status,
            'device_name' => $device->name,
            'athlete_name' => $device->athlete?->name ?? $trackingSession?->athlete?->name,
            'pairing_code' => $device->pairing_code,
            'session_status' => $trackingSession?->status,
            'active_session_token' => $tokenReturned ? $trackingSession->session_token : null,
            'debug_device_id' => $device->id,
            'debug_device_status' => $device->status,
            'debug_session_count' => $matchingSessions->count(),
            'debug_session_ids' => $debugSessionIds,
            'debug_token_returned' => $tokenReturned,
        ]);
    }

    private function resolveActiveSession(Device $device): array
    {
        $deviceSessions = $this->activeSessionQuery()
            ->where('device_id', $device->id)
            ->get();

        if ($deviceSessions->count() === 1) {
            return [
                'session' => $deviceSessions->first(),
                'sessions' => $deviceSessions,
                'matched_by' => 'device_id',
            ];
        }

        if ($deviceSessions->count() > 1 || ! $device->athlete_id) {
            return [
                'session' => null,
                'sessions' => $deviceSessions,
                'matched_by' => $deviceSessions->count() > 1 ? 'ambiguous' : 'none',
            ];
        }

        $unboundSessions = $this->activeSessionQuery()
            ->where('athlete_id', $device->athlete_id)
            ->whereNull('device_id')
            ->get();

        if ($unboundSessions->count() === 1) {
            $session = $unboundSessions->first();
            $session->forceFill(['device_id' => $device->id])->save();

            return [
                'session' => $session->fresh(),
                'sessions' => collect([$session->fresh()]),
                'matched_by' => 'athlete_unbound',
            ];
        }

        if ($unboundSessions->count() > 1) {
            return [
                'session' => null,
                'sessions' => $unboundSessions,
                'matched_by' => 'ambiguous',
            ];
        }

        $otherDeviceSessions = $this->activeSessionQuery()
            ->where('athlete_id', $device->athlete_id)
            ->whereNotNull('device_id')
            ->where('device_id', '!=', $device->id)
            ->get();

        if ($otherDeviceSessions->count() === 1) {
            $session = $otherDeviceSessions->first();

            Log::warning('garmin.discovery.session_device_reassigned', [
                'device_id' => $device->id,
                'previous_device_id' => $session->device_id,
                'athlete_id' => $device->athlete_id,
                'tracking_session_id' => $session->id,
            ]);

            $session->forceFill(['device_id' => $device->id])->save();

            return [
                'session' => $session->fresh(),
                'sessions' => collect([$session->fresh()]),
                'matched_by' => 'device_id',
            ];
        }

        return [
            'session' => null,
            'sessions' => $otherDeviceSessions,
            'matched_by' => $otherDeviceSessions->count() > 1 ? 'ambiguous' : 'none',
        ];
    }

    private function activeSessionQuery()
    {
        return TrackingSession::query()
            ->whereIn('status', ['active', 'armed', 'live', 'paused', 'stopped', 'stationary'])
            ->whereNull('ended_at')
            ->whereNotNull('session_token')
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(2);
    }

    private function deviceUuidPrefix(string $deviceUuid): string
    {
        return substr($deviceUuid, 0, 12);
    }
}
