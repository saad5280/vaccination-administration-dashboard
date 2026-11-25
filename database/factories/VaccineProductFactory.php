<?php

namespace Database\Factories;

use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VaccineProduct>
 */
class VaccineProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vaccine_id' => Vaccine::factory(),
            'manufacturer' => fake()->company(),
            'product_name' => fake()->words(2, true),
            'presentation' => fake()->randomElement(['liquid', 'powder']),
            'route' => 'IM',
            'storage_requirements' => null,
            'doses_per_vial' => 1,
            'stock_unit' => 'dose',
            'dose_quantity' => 0.5,
            'dose_unit' => 'mL',
            'is_active' => true,
        ];
    }
}
