<?php

namespace Database\Factories;

use App\Models\Child;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => 'CH-'.fake()->unique()->bothify('##########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'sex' => fake()->randomElement(['female', 'male', 'not_recorded']),
            'address' => fake()->streetAddress(),
            'locality' => fake()->city(),
            'registered_at' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
