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
        return [
            'uuid' => (string) Str::uuid(),
            'athlete_id' => Athlete::factory(),
            'name' => 'Garmin '.$this->faker->word(),
            'type' => 'watch',
            'provider' => 'garmin',
            'device_secret' => Str::random(64),
            'status' => 'active',
            'last_seen_at' => null,
            'metadata' => [],
        ];
    }
}
