<?php
namespace Database\Factories;

use App\Models\Athlete;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AthleteFactory extends Factory
{
    protected $model = Athlete::class;
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => $this->faker->name,
            'metadata' => [],
        ];
    }
}
