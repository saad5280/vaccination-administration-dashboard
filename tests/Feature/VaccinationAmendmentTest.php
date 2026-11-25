<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VaccinationAmendmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_admin_amends_record_preserving_original_and_reconciling_stock(): void
    {
        [$child, $centre, $vaccine, $originalBatch, $replacementBatch, $admin] = $this->setupCase();
        $original = $this->recordOriginal($child, $centre, $vaccine, $originalBatch, $admin);

        $this->actingAs($admin)
            ->get(route('children.vaccinations.amend', [$child, $original]))
            ->assertOk()->assertSee('Correct vaccination record');

        $this->actingAs($admin)->post(route('children.vaccinations.amend.store', [$child, $original]), [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDays(3)->format('Y-m-d\\TH:i'),
            'stock_batch_id' => $replacementBatch->id,
            'centre_id' => $centre->id,
            'correction_reason' => 'Incorrect lot was selected',
        ])->assertRedirect(route('children.vaccinations.index', $child));

        $original->refresh();
        $replacement = VaccinationRecord::query()->where('supersedes_record_id', $original->id)->firstOrFail();
        $this->assertSame('superseded', $original->status);
        $this->assertSame($admin->id, $replacement->administered_by, 'Correction must preserve who administered the dose.');
        $this->assertSame($admin->id, $replacement->created_by);
        $this->assertSame('Incorrect lot was selected', $replacement->correction_reason);
        $this->assertSame($replacementBatch->batch_number, $replacement->lot_number);
        $this->assertSame('100.000', $originalBatch->fresh()->available_quantity);
        $this->assertSame('99.000', $replacementBatch->fresh()->available_quantity);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_batch_id' => $originalBatch->id,
            'type' => 'return',
            'quantity' => '1.000',
            'reference_id' => $replacement->id,
        ]);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_batch_id' => $originalBatch->id,
            'type' => 'return',
            'related_transaction_id' => StockTransaction::query()
                ->where('reference_type', VaccinationRecord::class)
                ->where('reference_id', $original->id)
                ->where('type', 'issue')
                ->value('id'),
        ]);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_batch_id' => $replacementBatch->id,
            'type' => 'issue',
            'quantity' => '1.000',
            'reference_id' => $replacement->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vaccination.superseded', 'auditable_id' => $original->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vaccination.amended', 'auditable_id' => $replacement->id]);
        $this->assertSame(2, VaccinationRecord::query()->count());
    }

    public function test_missing_correction_reason_rejects_amendment_without_changing_record_or_stock(): void
    {
        [$child, $centre, $vaccine, $originalBatch, $replacementBatch, $admin] = $this->setupCase();
        $original = $this->recordOriginal($child, $centre, $vaccine, $originalBatch, $admin);

        $this->actingAs($admin)->from(route('children.vaccinations.amend', [$child, $original]))
            ->post(route('children.vaccinations.amend.store', [$child, $original]), [
                'vaccine_id' => $vaccine->id,
                'dose_number' => 1,
                'administered_at' => now()->subDays(3)->format('Y-m-d\\TH:i'),
                'stock_batch_id' => $replacementBatch->id,
                'centre_id' => $centre->id,
            ])->assertSessionHasErrors('correction_reason');

        $this->assertSame('completed', $original->fresh()->status);
        $this->assertSame('99.000', $originalBatch->fresh()->available_quantity);
        $this->assertSame('100.000', $replacementBatch->fresh()->available_quantity);
        $this->assertSame(1, VaccinationRecord::query()->count());
        $this->assertSame(1, StockTransaction::query()->count());
    }

    public function test_user_without_amend_permission_cannot_open_amendment_form(): void
    {
        [$child, $centre, $vaccine, $batch, , $admin] = $this->setupCase();
        $original = $this->recordOriginal($child, $centre, $vaccine, $batch, $admin);
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);

        $this->actingAs($vaccinator)->get(route('children.vaccinations.amend', [$child, $original]))->assertForbidden();
    }

    public function test_child_scoped_route_does_not_allow_amending_another_childs_record(): void
    {
        [$child, $centre, $vaccine, $batch, , $admin] = $this->setupCase();
        $original = $this->recordOriginal($child, $centre, $vaccine, $batch, $admin);
        $otherChild = Child::factory()->create(['date_of_birth' => '2020-01-01']);

        $this->actingAs($admin)->get(route('children.vaccinations.amend', [$otherChild, $original]))->assertNotFound();
    }

    public function test_superseded_record_cannot_be_amended_again(): void
    {
        [$child, $centre, $vaccine, $originalBatch, $replacementBatch, $admin] = $this->setupCase();
        $original = $this->recordOriginal($child, $centre, $vaccine, $originalBatch, $admin);
        $this->amend($admin, $child, $original, $vaccine, $centre, $replacementBatch);

        $this->actingAs($admin)->get(route('children.vaccinations.amend', [$child, $original]))
            ->assertStatus(422);
        $this->assertSame(2, VaccinationRecord::query()->count());
        $this->assertSame('100.000', $originalBatch->fresh()->available_quantity);
        $this->assertSame('99.000', $replacementBatch->fresh()->available_quantity);
    }

    /** @return array{Child,Centre,Vaccine,StockBatch,StockBatch,User} */
    private function setupCase(): array
    {
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $centre = Centre::factory()->create();
        $vaccine = Vaccine::factory()->create();
        $originalBatch = $this->batchFor($vaccine, $centre, 'ORIGINAL-LOT');
        $replacementBatch = $this->batchFor($vaccine, $centre, 'CORRECT-LOT');
        $admin = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record', 'vaccinations.amend']);

        return [$child, $centre, $vaccine, $originalBatch, $replacementBatch, $admin];
    }

    private function recordOriginal(Child $child, Centre $centre, Vaccine $vaccine, StockBatch $batch, User $admin): VaccinationRecord
    {
        $this->actingAs($admin)->post(route('children.vaccinations.store', $child), [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDays(3)->format('Y-m-d\\TH:i'),
            'stock_batch_id' => $batch->id,
            'centre_id' => $centre->id,
        ])->assertRedirect();

        return VaccinationRecord::query()->firstOrFail();
    }

    private function amend(User $admin, Child $child, VaccinationRecord $original, Vaccine $vaccine, Centre $centre, StockBatch $batch): void
    {
        $this->actingAs($admin)->post(route('children.vaccinations.amend.store', [$child, $original]), [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'administered_at' => now()->subDays(3)->format('Y-m-d\\TH:i'),
            'stock_batch_id' => $batch->id,
            'centre_id' => $centre->id,
            'correction_reason' => 'Correct the selected batch',
        ])->assertRedirect();
    }

    private function batchFor(Vaccine $vaccine, Centre $centre, string $batchNumber): StockBatch
    {
        $product = VaccineProduct::factory()->for($vaccine)->create([
            'manufacturer' => 'Test manufacturer', 'product_name' => 'Test presentation',
            'stock_unit' => 'dose', 'dose_quantity' => '0.500', 'dose_unit' => 'mL', 'route' => 'IM',
        ]);

        return StockBatch::factory()->for($product, 'product')->for($centre)->create(['batch_number' => $batchNumber]);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissionIds = collect($permissionNames)->map(fn (string $name): int => Permission::query()->firstOrCreate(
            ['name' => $name], ['label' => str($name)->replace('.', ' ')->title()->toString()],
        )->id);
        $role->permissions()->attach($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
