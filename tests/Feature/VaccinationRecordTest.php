<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VaccinationRecordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_vaccinator_records_dose_snapshots_and_stock_issue_atomically(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $scheduleDose = $this->approvedScheduleDose($vaccine);
        $batch = $this->batchFor($vaccine, $centre, ['batch_number' => 'LOT-RECORD-1']);

        $this->actingAs($vaccinator)->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, $scheduleDose, $batch, $centre))
            ->assertRedirect(route('children.vaccinations.index', $child));

        $record = VaccinationRecord::query()->firstOrFail();
        $this->assertSame($vaccinator->id, $record->administered_by);
        $this->assertSame('LOT-RECORD-1', $record->lot_number);
        $this->assertSame('Example manufacturer', $record->manufacturer);
        $this->assertSame('Example product', $record->product_name);
        $this->assertSame($scheduleDose->id, $record->schedule_dose_id);
        $this->assertSame('99.000', $batch->fresh()->available_quantity);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_batch_id' => $batch->id,
            'type' => 'issue',
            'quantity' => '1.000',
            'reference_type' => VaccinationRecord::class,
            'reference_id' => $record->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vaccination.recorded', 'auditable_id' => $record->id]);
    }

    public function test_unconfigured_dose_and_product_values_can_be_recorded_from_authorized_input(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $batch = $this->batchFor($vaccine, $centre);
        $data = $this->recordData($vaccine, null, $batch, $centre);
        $data['dose_quantity'] = '0.250';
        $data['dose_unit'] = 'mL';
        $data['route'] = 'Authority supplied route';

        $this->actingAs($vaccinator)->post(route('children.vaccinations.store', $child), $data)
            ->assertRedirect(route('children.vaccinations.index', $child));

        $this->assertDatabaseHas('vaccination_records', [
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'dose_quantity' => '0.250',
            'dose_unit' => 'mL',
            'route' => 'Authority supplied route',
            'schedule_dose_id' => null,
        ]);
    }

    public function test_duplicate_same_day_dose_is_rejected_without_a_second_stock_issue(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $dose = $this->approvedScheduleDose($vaccine);
        $batch = $this->batchFor($vaccine, $centre);
        $data = $this->recordData($vaccine, $dose, $batch, $centre);

        $this->actingAs($vaccinator)->post(route('children.vaccinations.store', $child), $data)->assertRedirect();
        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $data)
            ->assertSessionHasErrors('dose_number');

        $this->assertDatabaseCount('vaccination_records', 1);
        $this->assertDatabaseCount('stock_transactions', 1);
        $this->assertSame('99.000', $batch->fresh()->available_quantity);
    }

    public function test_insufficient_batch_stock_leaves_record_and_inventory_unchanged(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $batch = $this->batchFor($vaccine, $centre, ['available_quantity' => '0.500']);

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, null, $batch, $centre))
            ->assertSessionHasErrors(['stock_batch_id' => 'This batch does not have enough available stock for another dose.']);

        $this->assertDatabaseCount('vaccination_records', 0);
        $this->assertDatabaseCount('stock_transactions', 0);
        $this->assertSame('0.500', $batch->fresh()->available_quantity);
    }

    public function test_expired_batch_is_rejected_for_the_entered_administration_date(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $batch = $this->batchFor($vaccine, $centre, ['expires_at' => now()->subDays(2)->toDateString()]);

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, null, $batch, $centre))
            ->assertSessionHasErrors(['stock_batch_id' => 'The selected batch was expired on the administration date.']);

        $this->assertDatabaseCount('vaccination_records', 0);
        $this->assertSame('100.000', $batch->fresh()->available_quantity);
    }

    public function test_batch_must_match_both_vaccine_and_centre(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $otherCentre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $otherVaccine = Vaccine::factory()->create();
        $batch = $this->batchFor($otherVaccine, $otherCentre);

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, null, $batch, $centre))
            ->assertSessionHasErrors('stock_batch_id');

        $this->assertDatabaseCount('vaccination_records', 0);
    }

    public function test_configured_minimum_age_is_enforced(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => now()->subDays(30)->toDateString()]);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $dose = $this->approvedScheduleDose($vaccine, ['minimum_age_days' => 60]);
        $batch = $this->batchFor($vaccine, $centre);
        $data = $this->recordData($vaccine, $dose, $batch, $centre);
        $data['administered_at'] = now()->subDay()->format('Y-m-d\TH:i');

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $data)
            ->assertSessionHasErrors('administered_at');

        $this->assertDatabaseCount('vaccination_records', 0);
    }

    public function test_configured_minimum_interval_from_previous_dose_is_enforced(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $schedule = Schedule::factory()->create([
            'approval_status' => 'approved',
            'effective_from' => now()->subYear()->toDateString(),
        ]);
        $firstDose = ScheduleDose::factory()->for($schedule)->for($vaccine)->create(['dose_number' => 1]);
        $secondDose = ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 2,
            'minimum_interval_days' => 28,
        ]);
        $batch = $this->batchFor($vaccine, $centre);
        VaccinationRecord::factory()->create([
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'schedule_dose_id' => $firstDose->id,
            'dose_number' => 1,
            'administered_at' => now()->subDays(20),
            'stock_batch_id' => $batch->id,
            'centre_id' => $centre->id,
        ]);
        $data = $this->recordData($vaccine, $secondDose, $batch, $centre);
        $data['dose_number'] = 2;
        $data['administered_at'] = now()->subDay()->format('Y-m-d\TH:i');

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $data)
            ->assertSessionHasErrors('administered_at');

        $this->assertDatabaseCount('vaccination_records', 1);
    }

    public function test_future_or_pre_birth_administration_dates_are_rejected(): void
    {
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $batch = $this->batchFor($vaccine, $centre);

        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, null, $batch, $centre, now()->addDay()->format('Y-m-d\TH:i')))
            ->assertSessionHasErrors('administered_at');

        $child->update(['date_of_birth' => now()->subDay()->toDateString()]);
        $this->actingAs($vaccinator)->from(route('children.vaccinations.create', $child))
            ->post(route('children.vaccinations.store', $child), $this->recordData($vaccine, null, $batch, $centre, now()->subDays(2)->format('Y-m-d\TH:i')))
            ->assertSessionHasErrors('administered_at');

        $this->assertDatabaseCount('vaccination_records', 0);
    }

    public function test_history_and_record_forms_require_their_permissions_and_render_for_vaccinators(): void
    {
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $vaccine = Vaccine::factory()->create();
        $centre = Centre::factory()->create();
        $this->batchFor($vaccine, $centre);
        $viewer = $this->userWithPermissions(['children.view']);
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);

        $this->actingAs($viewer)->get(route('children.vaccinations.index', $child))->assertForbidden();
        $this->actingAs($vaccinator)->get(route('children.vaccinations.index', $child))->assertOk()->assertSee('Vaccination history');
        $this->actingAs($vaccinator)->get(route('children.vaccinations.create', $child))->assertOk()->assertSee('Record vaccination');
        $this->actingAs($vaccinator)->get(route('children.show', $child))->assertOk()->assertSee('Record dose');
        $this->actingAs($this->userWithPermissions(['children.view', 'vaccinations.view']))
            ->get(route('children.vaccinations.create', $child))->assertForbidden();
    }

    private function approvedScheduleDose(Vaccine $vaccine, array $attributes = []): ScheduleDose
    {
        $schedule = Schedule::factory()->create([
            'name' => 'Authorized programme schedule',
            'approval_status' => 'approved',
            'effective_from' => now()->subYear()->toDateString(),
            'effective_until' => null,
        ]);

        return ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 1,
            'minimum_age_days' => 0,
            ...$attributes,
        ]);
    }

    private function batchFor(Vaccine $vaccine, Centre $centre, array $overrides = []): StockBatch
    {
        $product = VaccineProduct::factory()->for($vaccine)->create([
            'manufacturer' => 'Example manufacturer',
            'product_name' => 'Example product',
            'stock_unit' => 'dose',
            'dose_quantity' => '0.500',
            'dose_unit' => 'mL',
            'route' => 'IM',
        ]);

        return StockBatch::factory()->for($product, 'product')->for($centre)->create($overrides);
    }

    private function recordData(Vaccine $vaccine, ?ScheduleDose $scheduleDose, StockBatch $batch, Centre $centre, ?string $administeredAt = null): array
    {
        return [
            'vaccine_id' => $vaccine->id,
            'schedule_dose_id' => $scheduleDose?->id,
            'dose_number' => $scheduleDose?->dose_number ?? 1,
            'administered_at' => $administeredAt ?? now()->subDay()->format('Y-m-d\TH:i'),
            'stock_batch_id' => $batch->id,
            'centre_id' => $centre->id,
        ];
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissionIds = collect($permissionNames)->map(fn (string $name): int => Permission::query()->firstOrCreate(
            ['name' => $name],
            ['label' => str($name)->replace('.', ' ')->title()->toString()],
        )->id);
        $role->permissions()->attach($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
