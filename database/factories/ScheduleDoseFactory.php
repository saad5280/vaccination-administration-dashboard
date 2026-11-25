<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleDose>
 */
class ScheduleDoseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'vaccine_id' => Vaccine::factory(),
            'dose_number' => 1,
            'recommended_age_days' => null,
            'minimum_age_days' => null,
            'interval_from_previous_days' => null,
            'minimum_interval_days' => null,
            'is_required' => true,
            'notes' => null,
        ];
    }
}
