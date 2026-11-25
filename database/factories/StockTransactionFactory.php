<?php

namespace Database\Factories;

use App\Models\Centre;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_batch_id' => StockBatch::factory(),
            'centre_id' => Centre::factory(),
            'type' => 'issue',
            'quantity' => '1.000',
            'reference_type' => null,
            'reference_id' => null,
            'reason' => null,
            'recorded_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
