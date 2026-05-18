<?php
namespace Database\Factories;

use App\Models\RaceEntry;
use App\Models\Event;
use App\Models\Athlete;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RaceEntryFactory extends Factory
{
    protected $model = RaceEntry::class;
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'event_id' => Event::factory(),
            'athlete_id' => Athlete::factory(),
            'metadata' => [],
        ];
    }
}
