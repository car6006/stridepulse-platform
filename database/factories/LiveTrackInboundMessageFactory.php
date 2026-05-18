<?php

namespace Database\Factories;

use App\Models\LiveTrackInboundMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveTrackInboundMessageFactory extends Factory
{
    protected $model = LiveTrackInboundMessage::class;

    public function definition(): array
    {
        return [
            'tracking_session_id' => null,
            'athlete_id' => null,
            'recipient_alias' => null,
            'from_email' => $this->faker->safeEmail,
            'subject' => 'Garmin LiveTrack',
            'raw_body' => $this->faker->paragraph,
            'extracted_url' => null,
            'received_at' => now(),
            'processed_at' => null,
            'status' => 'received',
            'metadata' => [],
        ];
    }
}
