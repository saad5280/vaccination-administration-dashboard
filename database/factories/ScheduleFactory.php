<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'version' => '1.0',
            'effective_from' => null,
            'effective_until' => null,
            'approval_status' => 'draft',
            'approved_by_name' => null,
            'approved_at' => null,
            'approval_reference' => null,
            'created_by' => null,
        ];
    }
}
