<?php

namespace Database\Seeders;

use App\Models\Centre;
use Illuminate\Database\Seeder;

class CentreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! config('vaccination.demo_mode')) {
            return;
        }

        $centres = [
            [
                'code' => 'DEMO-KHI-001',
                'name' => 'Demo Community Vaccination Centre - Karachi',
                'address' => 'Clifton Road',
                'locality' => 'Karachi',
            ],
            [
                'code' => 'DEMO-LHR-001',
                'name' => 'Demo Family Health Centre - Lahore',
                'address' => 'Model Town Link Road',
                'locality' => 'Lahore',
            ],
            [
                'code' => 'DEMO-ISB-001',
                'name' => 'Demo Immunization Clinic - Islamabad',
                'address' => 'G-10 Markaz',
                'locality' => 'Islamabad',
            ],
            [
                'code' => 'DEMO-PSH-001',
                'name' => 'Demo Child Wellness Centre - Peshawar',
                'address' => 'University Road',
                'locality' => 'Peshawar',
            ],
            [
                'code' => 'DEMO-MTN-001',
                'name' => 'Demo Primary Care Centre - Multan',
                'address' => 'Bosan Road',
                'locality' => 'Multan',
            ],
        ];

        foreach ($centres as $centre) {
            Centre::query()->updateOrCreate(
                ['code' => $centre['code']],
                [...$centre, 'is_active' => true],
            );
        }
    }
}
