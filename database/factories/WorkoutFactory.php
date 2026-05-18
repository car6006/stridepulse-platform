<?php
namespace Database\Factories;

use App\Models\Workout;
use App\Models\Athlete;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkoutFactory extends Factory
{
    protected $model = Workout::class;
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'athlete_id' => Athlete::factory(),
            'sport_id' => Sport::factory(),
            'name' => $this->faker->sentence(3),
            'metadata' => [],
        ];
    }
}
