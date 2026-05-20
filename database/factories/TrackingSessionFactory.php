<?php
namespace Database\Factories;

use App\Models\TrackingSession;
use App\Models\Athlete;
use App\Models\RaceEntry;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TrackingSessionFactory extends Factory
{
    protected $model = TrackingSession::class;
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'session_token' => Str::random(40),
            'athlete_id' => Athlete::factory(),
            'device_id' => null,
            'sport_id' => Sport::factory(),
            'race_entry_id' => null,
            'device_source' => 'garmin_connect_iq',
            'activity_type' => 'run',
            'status' => 'active',
            'started_at' => $this->faker->dateTime,
            'last_seen_at' => $this->faker->dateTime,
            'ended_at' => null,
            'livetrack_url' => null,
            'livetrack_received_at' => null,
            'livetrack_source_email' => null,
            'telemetry_source' => 'connect_iq',
            'last_direct_telemetry_at' => null,
            'last_movement_at' => null,
            'last_status_changed_at' => null,
            'notification_suppressed_at' => null,
            'notification_state' => [],
            'metadata' => [],
        ];
    }
}
