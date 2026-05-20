<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\GarminDeviceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $status = $device->status === 'active' ? 'active' : 'unclaimed';

        return response()->json([
            'ok' => true,
            'status' => $status,
            'device_name' => $device->name,
            'athlete_name' => $device->athlete?->name,
            'active_session_token' => $status === 'active' ? $this->singleActiveSessionToken($device) : null,
        ]);
    }

    private function singleActiveSessionToken(Device $device): ?string
    {
        $sessions = $device->trackingSessions()
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereNotNull('session_token')
            ->limit(2)
            ->get(['session_token']);

        if ($sessions->count() !== 1) {
            return null;
        }

        return $sessions->first()->session_token;
    }
}
