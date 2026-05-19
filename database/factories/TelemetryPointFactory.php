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
            'elapsed_time_seconds' => $this->faker->numberBetween(0, 20000),
            'distance_m' => $this->faker->randomFloat(2, 0, 42195),
            'pace_sec_per_km' => $this->faker->numberBetween(180, 600),
            'average_pace_sec_per_km' => $this->faker->numberBetween(180, 600),
            'current_speed_mps' => $this->faker->randomFloat(3, 0, 8),
            'heart_rate_bpm' => $this->faker->numberBetween(80, 190),
            'avg_heart_rate_bpm' => $this->faker->numberBetween(80, 170),
            'cadence' => $this->faker->numberBetween(140, 190),
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'altitude_m' => $this->faker->randomFloat(2, 0, 1800),
            'heading_degrees' => $this->faker->randomFloat(2, 0, 360),
            'ascent_m' => $this->faker->randomFloat(2, 0, 1200),
            'descent_m' => $this->faker->randomFloat(2, 0, 1200),
            'calories' => $this->faker->numberBetween(0, 3000),
            'lap_number' => $this->faker->numberBetween(1, 20),
            'gps_status' => 'LOCK',
            'battery_percent' => $this->faker->numberBetween(10, 100),
            'device_model' => 'fr965',
            'raw_payload' => ['sample' => true],
            'metadata' => [],
        ];
    }
}
