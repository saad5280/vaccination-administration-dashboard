<?php

namespace Database\Factories;

use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccine>
 */
class VaccineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('VAC-####'),
            'name' => fake()->unique()->words(2, true),
            'target_disease' => fake()->words(2, true),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
