<?php

namespace Database\Factories;

use App\Models\Athlete;
use App\Models\AthleteActivity;
use App\Models\Sport;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AthleteActivityFactory extends Factory
{
    protected $model = AthleteActivity::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'athlete_id' => Athlete::factory(),
            'tracking_session_id' => TrackingSession::factory(),
            'sport_id' => Sport::factory(),
            'source' => 'connect_iq',
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_seconds' => 3600,
            'distance_m' => 10000,
            'average_pace_sec_per_km' => 360,
            'average_heart_rate_bpm' => 145,
            'max_heart_rate_bpm' => 172,
            'calories' => 700,
            'ascent_m' => 120,
            'descent_m' => 115,
            'start_latitude' => -33.9249,
            'start_longitude' => 18.4241,
            'end_latitude' => -33.9250,
            'end_longitude' => 18.4242,
            'summary_payload' => ['sample' => true],
        ];
    }
}
