<?php

namespace Database\Factories;

use App\Models\Centre;
use App\Models\StockBatch;
use App\Models\VaccineProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vaccine_product_id' => VaccineProduct::factory(),
            'centre_id' => Centre::factory(),
            'batch_number' => fake()->unique()->bothify('BATCH-#####'),
            'supplier' => fake()->company(),
            'received_at' => now()->subDays(30)->toDateString(),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'quantity_received' => '100.000',
            'available_quantity' => '100.000',
            'storage_location' => 'Test storage location',
            'status' => 'active',
        ];
    }
}
