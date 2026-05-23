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

        $matchingSessions = $device->isClaimedForLifecycle()
            ? $this->matchingActiveSessions($device)
            : collect();

        $trackingSession = $matchingSessions->count() === 1
            ? $matchingSessions->first()
            : null;

        $tokenReturned = $trackingSession instanceof TrackingSession && filled($trackingSession->session_token);

        $debugSessionIds = $matchingSessions->pluck('id')->values()->all();

        Log::info('garmin.discovery', [
            'device_uuid_prefix' => $this->deviceUuidPrefix($validated['device_uuid']),
            'device_id' => $device->id,
            'device_status' => $device->status,
            'athlete_id' => $device->athlete_id,
            'matching_session_count' => $matchingSessions->count(),
            'matching_session_ids' => $debugSessionIds,
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

    private function matchingActiveSessions(Device $device): Collection
    {
        return $device->trackingSessions()
            ->whereIn('status', ['active', 'armed', 'live'])
            ->whereNull('ended_at')
            ->limit(2)
            ->get(['id', 'athlete_id', 'status', 'session_token']);
    }

    private function deviceUuidPrefix(string $deviceUuid): string
    {
        return substr($deviceUuid, 0, 12);
    }
}
