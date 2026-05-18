<?php
namespace Database\Factories;

use App\Models\TelemetryPoint;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelemetryPointFactory extends Factory
{
    protected $model = TelemetryPoint::class;
    public function definition(): array
    {
        return [
            'tracking_session_id' => TrackingSession::factory(),
            'ingestion_id' => $this->faker->uuid,
            'recorded_at' => $this->faker->dateTime,
            'elapsed_seconds' => $this->faker->numberBetween(0, 20000),
            'distance_m' => $this->faker->randomFloat(2, 0, 42195),
            'pace_sec_per_km' => $this->faker->numberBetween(180, 600),
            'heart_rate_bpm' => $this->faker->numberBetween(80, 190),
            'avg_heart_rate_bpm' => $this->faker->numberBetween(80, 170),
            'cadence' => $this->faker->numberBetween(140, 190),
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'gps_status' => 'LOCK',
            'battery_percent' => $this->faker->numberBetween(10, 100),
            'device_model' => 'fr965',
            'raw_payload' => ['sample' => true],
            'metadata' => [],
        ];
    }
}
