<?php
namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => $this->faker->sentence(2),
            'event_date' => $this->faker->date(),
            'metadata' => [],
        ];
    }
}
