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

        Log::info('Garmin device discovery session lookup completed', [
            'device_id' => $device->id,
            'device_status' => $device->status,
            'matching_active_sessions_count' => $matchingSessions->count(),
            'token_returned' => $tokenReturned,
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
}
