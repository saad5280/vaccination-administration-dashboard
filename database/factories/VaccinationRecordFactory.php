<?php

namespace Database\Factories;

use App\Models\Centre;
use App\Models\Child;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VaccinationRecord>
 */
class VaccinationRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'vaccine_id' => Vaccine::factory(),
            'schedule_dose_id' => null,
            'dose_number' => 1,
            'administered_at' => now()->subDays(7),
            'stock_batch_id' => StockBatch::factory(),
            'lot_number' => fake()->bothify('LOT-####'),
            'batch_expiry_date' => now()->addMonths(6)->toDateString(),
            'manufacturer' => fake()->company(),
            'product_name' => fake()->words(2, true),
            'dose_quantity' => '0.500',
            'dose_unit' => 'mL',
            'route' => 'IM',
            'administration_site' => 'Upper arm',
            'centre_id' => Centre::factory(),
            'administered_by' => User::factory(),
            'status' => 'completed',
            'notes' => null,
            'supersedes_record_id' => null,
            'correction_reason' => null,
            'created_by' => User::factory(),
        ];
    }
}
