<?php

namespace Database\Factories;

use App\Models\Athlete;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'device_uuid' => $uuid,
            'pairing_code' => Device::derivePairingCode($uuid),
            'athlete_id' => Athlete::factory(),
            'name' => 'Garmin '.$this->faker->word(),
            'type' => 'watch',
            'provider' => 'garmin',
            'device_secret' => Str::random(64),
            'status' => Device::STATUS_CLAIMED,
            'last_seen_at' => null,
            'last_claimed_at' => now(),
            'last_telemetry_at' => null,
            'archived_at' => null,
            'metadata' => [],
        ];
    }
}
