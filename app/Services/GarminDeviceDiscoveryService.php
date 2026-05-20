<?php

namespace App\Services;

use App\Models\Device;

class GarminDeviceDiscoveryService
{
    public function resolve(array $validated): Device|bool|null
    {
        if (empty($validated['device_uuid']) && empty($validated['device_secret'])) {
            return null;
        }

        $deviceUuid = $validated['device_uuid'] ?? null;

        $device = Device::query()
            ->where('device_uuid', $deviceUuid)
            ->first();

        if (! $device) {
            return Device::query()->create([
                'uuid' => (string) str()->uuid(),
                'device_uuid' => $deviceUuid,
                'athlete_id' => null,
                'name' => $validated['device_model'] ?? 'Unknown Garmin Device',
                'type' => 'watch',
                'provider' => 'garmin',
                'device_secret' => $validated['device_secret'] ?? str()->random(64),
                'status' => 'unclaimed',
                'last_seen_at' => now(),
                'metadata' => $this->metadataForNewDevice($validated),
            ]);
        }

        if ($device->status === 'active' && ! empty($validated['device_secret']) && ! hash_equals($device->device_secret, $validated['device_secret'])) {
            return false;
        }

        $metadata = $this->mergeMetadata($device->metadata ?? [], $validated);
        $updates = [
            'last_seen_at' => now(),
            'metadata' => $metadata,
        ];

        if ($device->status === 'unclaimed' && ($device->name === 'Unknown Garmin Device' || $device->name === '') && ! empty($validated['device_model'])) {
            $updates['name'] = $validated['device_model'];
        }

        $device->forceFill($updates)->save();

        return $device;
    }

    private function metadataForNewDevice(array $validated): array
    {
        return $this->mergeMetadata([
            'first_seen_payload' => array_intersect_key($validated, array_flip([
                'device_uuid',
                'device_model',
                'app_version',
                'firmware_version',
                'gps_status',
                'battery_percent',
            ])),
        ], $validated);
    }

    private function mergeMetadata(array $metadata, array $validated): array
    {
        foreach (['device_model', 'app_version', 'firmware_version', 'battery_percent'] as $key) {
            if (array_key_exists($key, $validated)) {
                $metadata[$key] = $validated[$key];
            }
        }

        return $metadata;
    }
}
