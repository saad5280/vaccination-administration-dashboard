<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildScheduleAssignment;
use App\Models\FollowUp;
use App\Models\Guardian;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\StockReorderLevel;
use App\Models\StockTransaction;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class DemoWorkspaceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $centre = Centre::query()->where('code', 'DEMO-ISB-001')->firstOrFail();
        $administratorId = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.name', 'administrator'))
            ->value('id');
        $vaccinator = User::query()->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.name', 'vaccinator'))
            ->orderBy('id')->first();
        $registrationUser = User::query()->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.name', 'registration'))
            ->orderBy('id')->first();

        User::query()->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('roles.name', ['vaccinator', 'registration']))
            ->get()->each(fn (User $user) => $user->centres()->syncWithoutDetaching([$centre->id]));

        $vaccine = Vaccine::query()->where('code', 'DEMO-HBV')->firstOrFail();
        $schedule = Schedule::query()->updateOrCreate(
            ['name' => 'Demo Routine Schedule', 'version' => 'DEMO-1'],
            [
                'effective_from' => today()->subYear()->toDateString(),
                'effective_until' => null,
                'approval_status' => 'draft',
                'approved_by_name' => null,
                'approved_by_id' => null,
                'approved_at' => null,
                'approval_reference' => null,
                'created_by' => $administratorId,
            ],
        );
        ScheduleDose::query()->updateOrCreate(
            ['schedule_id' => $schedule->id, 'vaccine_id' => $vaccine->id, 'dose_number' => 1],
            [
                'recommended_age_days' => 90,
                'minimum_age_days' => 0,
                'is_required' => true,
                'notes' => 'Demo schedule data only; not a clinical recommendation.',
            ],
        );

        $childSpecs = [
            ['key' => 'overdue', 'registration_id' => 'DEMO-CHILD-001', 'last_name' => 'A', 'age_days' => 200],
            ['key' => 'upcoming', 'registration_id' => 'DEMO-CHILD-002', 'last_name' => 'B', 'age_days' => 40],
            ['key' => 'complete', 'registration_id' => 'DEMO-CHILD-003', 'last_name' => 'C', 'age_days' => 600],
            ['key' => 'due', 'registration_id' => 'DEMO-CHILD-004', 'last_name' => 'D', 'age_days' => 90],
        ];
        $children = [];
        $guardians = [];

        foreach ($childSpecs as $index => $spec) {
            $child = Child::query()->updateOrCreate(
                ['registration_id' => $spec['registration_id']],
                [
                    'first_name' => 'Demo Child',
                    'last_name' => $spec['last_name'],
                    'date_of_birth' => today()->subDays($spec['age_days'])->toDateString(),
                    'sex' => 'Not specified',
                    'address' => 'Demo address',
                    'locality' => 'Islamabad',
                    'centre_id' => $centre->id,
                    'registered_at' => today()->toDateString(),
                    'status' => 'active',
                    'internal_notes' => 'DEMO DATA ONLY - not a real child record.',
                    'created_by' => $administratorId,
                ],
            );
            $children[$spec['key']] = $child;

            $guardian = Guardian::query()->updateOrCreate(
                ['email' => 'demo-guardian-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).'@example.test'],
                [
                    'full_name' => 'Demo Guardian '.($index + 1),
                    'phone' => '+9200000000'.($index + 1),
                    'address' => 'Demo address, Islamabad',
                    'preferred_contact_method' => 'in_app',
                    'preferred_language' => 'en',
                ],
            );
            $guardians[$spec['key']] = $guardian;

            $child->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'relationship' => 'Parent',
                    'is_primary' => true,
                    'consent_status' => 'unknown',
                    'consent_recorded_by' => $administratorId,
                ],
            ]);

        }

        ChildScheduleAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('child_id', collect($children)->pluck('id'))
            ->delete();

        $productNames = [
            'DEMO-HBV' => 'Demo Hepatitis B Sample Product',
            'DEMO-BCG' => 'Demo BCG Sample Product',
            'DEMO-IPV' => 'Demo Polio Sample Product',
            'DEMO-PENTA' => 'Demo Pentavalent Sample Product',
            'DEMO-MEASLES' => 'Demo Measles Sample Product',
        ];
        $products = [];
        $batches = [];

        foreach ($productNames as $vaccineCode => $productName) {
            $productVaccine = Vaccine::query()->where('code', $vaccineCode)->firstOrFail();
            $product = VaccineProduct::query()->where('vaccine_id', $productVaccine->id)
                ->where('product_name', $productName)->firstOrFail();
            $products[$vaccineCode] = $product;
            $quantity = $vaccineCode === 'DEMO-PENTA' ? 5 : 100;
            $batch = StockBatch::query()->updateOrCreate(
                ['vaccine_product_id' => $product->id, 'centre_id' => $centre->id, 'batch_number' => 'DEMO-BATCH-'.$vaccineCode],
                [
                    'supplier' => 'Demo supplier',
                    'received_at' => today()->subDays(30)->toDateString(),
                    'expires_at' => today()->addDays($vaccineCode === 'DEMO-PENTA' ? 120 : 365)->toDateString(),
                    'quantity_received' => $quantity,
                    'available_quantity' => $quantity,
                    'storage_location' => 'Demo cold room',
                    'status' => 'active',
                ],
            );
            $batches[$vaccineCode] = $batch;

            StockTransaction::query()->updateOrCreate(
                ['stock_batch_id' => $batch->id, 'type' => 'receipt', 'reference_type' => 'demo_seed', 'reference_id' => $product->id],
                [
                    'centre_id' => $centre->id,
                    'quantity' => $quantity,
                    'reason' => 'Demo stock receipt; sample data only.',
                    'recorded_by' => $administratorId,
                    'occurred_at' => now()->subDays(30),
                ],
            );

            StockReorderLevel::query()->updateOrCreate(
                ['vaccine_product_id' => $product->id, 'centre_id' => $centre->id],
                ['minimum_quantity' => $vaccineCode === 'DEMO-PENTA' ? 10 : 20, 'updated_by' => $administratorId],
            );
        }

        $lowStockBatch = StockBatch::query()->updateOrCreate(
            ['vaccine_product_id' => $products['DEMO-PENTA']->id, 'centre_id' => $centre->id, 'batch_number' => 'DEMO-BATCH-PENTA-LOW'],
            [
                'supplier' => 'Demo supplier',
                'received_at' => today()->subDays(20)->toDateString(),
                'expires_at' => today()->addDays(20)->toDateString(),
                'quantity_received' => 5,
                'available_quantity' => 2,
                'storage_location' => 'Demo cold room',
                'status' => 'active',
            ],
        );
        StockTransaction::query()->updateOrCreate(
            ['stock_batch_id' => $lowStockBatch->id, 'type' => 'receipt', 'reference_type' => 'demo_seed', 'reference_id' => $products['DEMO-PENTA']->id],
            [
                'centre_id' => $centre->id,
                'quantity' => 5,
                'reason' => 'Demo low-stock receipt; sample data only.',
                'recorded_by' => $administratorId,
                'occurred_at' => now()->subDays(20),
            ],
        );

        $vaccinationSpecs = [
            ['child' => 'overdue', 'vaccine' => 'DEMO-BCG', 'age_days' => 14],
            ['child' => 'upcoming', 'vaccine' => 'DEMO-BCG', 'age_days' => 14],
            ['child' => 'complete', 'vaccine' => 'DEMO-HBV', 'age_days' => 90],
            ['child' => 'due', 'vaccine' => 'DEMO-BCG', 'age_days' => 14],
        ];

        foreach ($vaccinationSpecs as $spec) {
            $child = $children[$spec['child']];
            $recordVaccine = Vaccine::query()->where('code', $spec['vaccine'])->firstOrFail();
            $product = $products[$spec['vaccine']];
            $batch = $batches[$spec['vaccine']];
            $administeredAt = $child->date_of_birth->copy()->addDays($spec['age_days'])->setTime(10, 0);

            $record = VaccinationRecord::query()->updateOrCreate(
                ['child_id' => $child->id, 'vaccine_id' => $recordVaccine->id, 'dose_number' => 1],
                [
                    'schedule_dose_id' => null,
                    'administered_at' => $administeredAt,
                    'stock_batch_id' => $batch->id,
                    'lot_number' => $batch->batch_number,
                    'batch_expiry_date' => $batch->expires_at,
                    'manufacturer' => $product->manufacturer,
                    'product_name' => $product->product_name,
                    'dose_quantity' => 1,
                    'dose_unit' => 'demo-unit',
                    'route' => 'Demo route',
                    'administration_site' => 'Demo site',
                    'centre_id' => $centre->id,
                    'administered_by' => $vaccinator?->id,
                    'status' => 'completed',
                    'notes' => 'DEMO DATA ONLY - sample entry, not a real administration.',
                    'created_by' => $administratorId,
                ],
            );

            StockTransaction::query()->updateOrCreate(
                ['stock_batch_id' => $batch->id, 'type' => 'issue', 'reference_type' => VaccinationRecord::class, 'reference_id' => $record->id],
                [
                    'centre_id' => $centre->id,
                    'quantity' => 1,
                    'reason' => 'Demo vaccination record; sample data only.',
                    'recorded_by' => $vaccinator?->id ?? $administratorId,
                    'occurred_at' => $administeredAt,
                ],
            );
        }

        $batches['DEMO-BCG']->update(['available_quantity' => 97]);
        $batches['DEMO-HBV']->update(['available_quantity' => 99]);

        $appointmentSpecs = [
            ['key' => 'missed', 'child' => 'overdue', 'scheduled_at' => now()->subDay()->setTime(9, 0), 'status' => 'missed'],
            ['key' => 'upcoming', 'child' => 'upcoming', 'scheduled_at' => now()->addDays(5)->setTime(9, 0), 'status' => 'booked'],
            ['key' => 'due', 'child' => 'due', 'scheduled_at' => now()->addDays(2)->setTime(9, 0), 'status' => 'booked'],
        ];
        $appointments = [];

        foreach ($appointmentSpecs as $spec) {
            $appointment = Appointment::query()->updateOrCreate(
                ['child_id' => $children[$spec['child']]->id, 'notes' => 'Demo appointment: '.$spec['key']],
                [
                    'schedule_dose_id' => null,
                    'centre_id' => $centre->id,
                    'vaccinator_id' => $vaccinator?->id,
                    'scheduled_at' => $spec['scheduled_at'],
                    'status' => $spec['status'],
                    'created_by' => $administratorId,
                ],
            );
            $appointments[$spec['key']] = $appointment;
        }

        $followUpNotes = 'Demo follow-up after missed appointment; sample data only.';
        FollowUp::query()->updateOrCreate(
            ['child_id' => $children['overdue']->id, 'notes' => $followUpNotes],
            [
                'appointment_id' => $appointments['missed']->id,
                'schedule_dose_id' => null,
                'assigned_to' => $registrationUser?->id ?? $vaccinator?->id,
                'outcome' => 'follow_up_required',
                'next_action_at' => today()->addDays(2)->toDateString(),
                'recorded_by' => $administratorId,
            ],
        );

        $template = ReminderTemplate::query()->updateOrCreate(
            ['name' => 'Demo vaccination reminder'],
            [
                'channel' => 'in_app',
                'language' => 'en',
                'body' => 'DEMO DATA ONLY - sample vaccination appointment reminder.',
                'is_active' => true,
            ],
        );
        Reminder::query()->updateOrCreate(
            ['idempotency_key' => 'demo-vaccination-reminder-001'],
            [
                'child_id' => $children['upcoming']->id,
                'guardian_id' => $guardians['upcoming']->id,
                'schedule_dose_id' => null,
                'appointment_id' => $appointments['upcoming']->id,
                'template_id' => $template->id,
                'channel' => 'in_app',
                'status' => 'pending',
                'scheduled_for' => now()->subMinute(),
                'created_by' => $administratorId,
                'template_name_snapshot' => $template->name,
                'template_body_snapshot' => $template->body,
            ],
        );
    }
}
