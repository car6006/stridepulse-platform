<?php

namespace App\Services;

use App\Models\Device;
use App\Models\TrackingSession;

class GarminDeviceDiscoveryService
{
    public function resolve(array $validated): Device|bool|null
    {
        if (empty($validated['device_uuid']) && empty($validated['device_secret'])) {
            return null;
        }

        $deviceUuid = $validated['device_uuid'] ?? null;

        $pairingCode = Device::derivePairingCode($deviceUuid);

        $device = Device::query()
            ->where('device_uuid', $deviceUuid)
            ->first();

        if (! $device) {
            $replacementFor = $this->findByPairingCode($pairingCode);

            if ($replacementFor instanceof Device) {
                $device = Device::query()->create([
                    'uuid' => (string) str()->uuid(),
                    'device_uuid' => $deviceUuid,
                    'pairing_code' => $pairingCode,
                    'athlete_id' => $replacementFor->athlete_id,
                    'name' => $validated['device_model'] ?? $replacementFor->name,
                    'type' => $replacementFor->type ?: 'watch',
                    'provider' => 'garmin',
                    'device_secret' => $validated['device_secret'] ?? $replacementFor->device_secret,
                    'status' => $replacementFor->athlete_id ? Device::STATUS_CLAIMED : Device::STATUS_UNCLAIMED,
                    'last_seen_at' => now(),
                    'last_claimed_at' => $replacementFor->athlete_id ? now() : null,
                    'metadata' => $this->metadataForNewDevice($validated, [
                        'replaced_device_id' => $replacementFor->id,
                    ]),
                ]);

                $replacementFor->forceFill([
                    'status' => Device::STATUS_ARCHIVED,
                    'archived_at' => now(),
                    'metadata' => array_merge($replacementFor->metadata ?? [], [
                        'replaced_by_device_id' => $device->id,
                        'replaced_by_device_uuid' => $deviceUuid,
                    ]),
                ])->save();

                TrackingSession::query()
                    ->where('device_id', $replacementFor->id)
                    ->where('status', 'active')
                    ->whereNull('ended_at')
                    ->update(['device_id' => $device->id]);

                return $device;
            }

            return Device::query()->create([
                'uuid' => (string) str()->uuid(),
                'device_uuid' => $deviceUuid,
                'pairing_code' => $pairingCode,
                'athlete_id' => null,
                'name' => $validated['device_model'] ?? 'Unknown Garmin Device',
                'type' => 'watch',
                'provider' => 'garmin',
                'device_secret' => $validated['device_secret'] ?? str()->random(64),
                'status' => Device::STATUS_UNCLAIMED,
                'last_seen_at' => now(),
                'metadata' => $this->metadataForNewDevice($validated),
            ]);
        }

        if ($device->isClaimedForLifecycle() && ! empty($validated['device_secret']) && ! hash_equals($device->device_secret, $validated['device_secret'])) {
            return false;
        }

        $metadata = $this->mergeMetadata($device->metadata ?? [], $validated);
        $updates = [
            'last_seen_at' => now(),
            'pairing_code' => $device->pairing_code ?? $pairingCode,
            'metadata' => $metadata,
        ];

        if ($device->status === Device::STATUS_UNCLAIMED && ($device->name === 'Unknown Garmin Device' || $device->name === '') && ! empty($validated['device_model'])) {
            $updates['name'] = $validated['device_model'];
        }

        $device->forceFill($updates)->save();

        return $device;
    }

    private function findByPairingCode(string $pairingCode): ?Device
    {
        return Device::query()
            ->where('provider', 'garmin')
            ->where(function ($query) {
                $query->whereNull('archived_at')
                    ->where('status', '!=', Device::STATUS_ARCHIVED);
            })
            ->latest('last_seen_at')
            ->latest('id')
            ->get()
            ->first(fn (Device $device) => $device->pairing_code === $pairingCode);
    }

    private function metadataForNewDevice(array $validated, array $extra = []): array
    {
        return array_merge($this->mergeMetadata([
            'first_seen_payload' => array_intersect_key($validated, array_flip([
                'device_uuid',
                'device_model',
                'app_version',
                'firmware_version',
                'gps_status',
                'battery_percent',
            ])),
        ], $validated), $extra);
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
