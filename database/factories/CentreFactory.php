<?php

namespace Database\Factories;

use App\Models\Centre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Centre>
 */
class CentreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CTR-####'),
            'name' => fake()->city().' Vaccination Centre',
            'phone' => fake()->optional()->numerify('+###########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->streetAddress(),
            'locality' => fake()->city(),
            'is_active' => true,
        ];
    }
}
