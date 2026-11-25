<?php

namespace Database\Seeders;

use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Database\Seeder;

class DemoVaccineProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! config('vaccination.demo_mode')) {
            return;
        }

        $catalogue = [
            [
                'code' => 'DEMO-HBV',
                'name' => 'Demo Hepatitis B Vaccine',
                'product_name' => 'Demo Hepatitis B Sample Product',
            ],
            [
                'code' => 'DEMO-BCG',
                'name' => 'Demo BCG Vaccine',
                'product_name' => 'Demo BCG Sample Product',
            ],
            [
                'code' => 'DEMO-IPV',
                'name' => 'Demo Inactivated Polio Vaccine',
                'product_name' => 'Demo Polio Sample Product',
            ],
            [
                'code' => 'DEMO-PENTA',
                'name' => 'Demo Pentavalent Vaccine',
                'product_name' => 'Demo Pentavalent Sample Product',
            ],
            [
                'code' => 'DEMO-MEASLES',
                'name' => 'Demo Measles Vaccine',
                'product_name' => 'Demo Measles Sample Product',
            ],
        ];

        foreach ($catalogue as $item) {
            $vaccine = Vaccine::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'target_disease' => 'Demo data',
                    'notes' => 'Sample catalogue entry for demonstration only; not for clinical use.',
                    'is_active' => true,
                ],
            );

            VaccineProduct::query()->updateOrCreate(
                [
                    'vaccine_id' => $vaccine->id,
                    'product_name' => $item['product_name'],
                ],
                [
                    'manufacturer' => 'Demo supplier',
                    'presentation' => 'Demo presentation',
                    'route' => 'Demo route',
                    'dose_quantity' => 1,
                    'dose_unit' => 'demo-unit',
                    'stock_unit' => 'dose',
                    'is_active' => true,
                ],
            );
        }
    }
}
