<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stock_receipt_creates_batch_ledger_entry_and_audit_record(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $this->actingAs($officer)->get(route('inventory.receipts.create'))->assertOk()->assertSee('Receive vaccine stock');

        $this->actingAs($officer)->post(route('inventory.receipts.store'), $this->receiptData($product, $centre))
            ->assertRedirect(route('inventory.index'));

        $batch = StockBatch::query()->firstOrFail();
        $this->assertSame('100.000', $batch->available_quantity);
        $this->assertDatabaseHas('stock_transactions', [
            'stock_batch_id' => $batch->id,
            'type' => 'receipt',
            'quantity' => '100.000',
            'reference_type' => StockBatch::class,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.received', 'auditable_id' => $batch->id]);
    }

    public function test_receipt_rejects_expired_lots_and_duplicate_lot_at_same_centre(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $data = $this->receiptData($product, $centre);

        $this->actingAs($officer)->from(route('inventory.receipts.create'))
            ->post(route('inventory.receipts.store'), [...$data, 'expires_at' => today()->toDateString()])
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($officer)->post(route('inventory.receipts.store'), $data)->assertRedirect();
        $this->actingAs($officer)->from(route('inventory.receipts.create'))
            ->post(route('inventory.receipts.store'), $data)
            ->assertSessionHasErrors('batch_number');

        $this->assertDatabaseCount('stock_batches', 1);
        $this->assertDatabaseCount('stock_transactions', 1);
    }

    public function test_issue_and_partial_return_are_linked_and_return_cannot_exceed_issue(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $batch = $this->batch($product, $centre, ['available_quantity' => '10.000', 'quantity_received' => '10.000']);

        $this->actingAs($officer)->get(route('inventory.transactions.create'))->assertOk()->assertSee('Record stock movement');
        $this->actingAs($officer)->post(route('inventory.transactions.store'), $this->movementData('issue', $batch, '4.000'))
            ->assertRedirect(route('inventory.transactions.index'));
        $issue = StockTransaction::query()->where('reference_type', 'inventory_issue')->firstOrFail();

        $this->actingAs($officer)->post(route('inventory.transactions.store'), [
            ...$this->movementData('return', $batch, '3.000'),
            'related_transaction_id' => $issue->id,
        ])->assertRedirect();
        $return = StockTransaction::query()->where('type', 'return')->firstOrFail();
        $this->assertSame($issue->id, $return->related_transaction_id);
        $this->assertSame('9.000', $batch->fresh()->available_quantity);

        $this->actingAs($officer)->from(route('inventory.transactions.create'))
            ->post(route('inventory.transactions.store'), [
                ...$this->movementData('return', $batch, '2.000'),
                'related_transaction_id' => $issue->id,
            ])->assertSessionHasErrors('quantity');
        $this->assertSame('9.000', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('stock_transactions', 2);
    }

    public function test_issue_cannot_make_stock_negative_and_quarantined_batches_are_blocked(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $batch = $this->batch($product, $centre, ['available_quantity' => '2.000', 'quantity_received' => '2.000']);

        $this->actingAs($officer)->from(route('inventory.transactions.create'))
            ->post(route('inventory.transactions.store'), $this->movementData('issue', $batch, '3.000'))
            ->assertSessionHasErrors('quantity');
        $this->assertSame('2.000', $batch->fresh()->available_quantity);

        $this->actingAs($officer)->post(route('inventory.batches.status', $batch), [
            'status' => 'quarantined', 'reason' => 'Quality review pending',
        ])->assertRedirect();
        $this->actingAs($officer)->from(route('inventory.transactions.create'))
            ->post(route('inventory.transactions.store'), $this->movementData('issue', $batch, '1.000'))
            ->assertSessionHasErrors('stock_batch_id');
        $this->assertSame('2.000', $batch->fresh()->available_quantity);
    }

    public function test_transfer_moves_stock_between_centre_batches_and_writes_paired_ledger_entries(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $sourceCentre] = $this->productAndCentre();
        $destinationCentre = Centre::factory()->create(['is_active' => true]);
        $source = $this->batch($product, $sourceCentre, ['available_quantity' => '10.000', 'quantity_received' => '10.000']);

        $this->actingAs($officer)->post(route('inventory.transactions.store'), [
            ...$this->movementData('transfer', $source, '4.000'),
            'destination_centre_id' => $destinationCentre->id,
        ])->assertRedirect();

        $destination = StockBatch::query()->where('centre_id', $destinationCentre->id)->where('batch_number', $source->batch_number)->firstOrFail();
        $transfer = StockTransfer::query()->firstOrFail();
        $this->assertSame('6.000', $source->fresh()->available_quantity);
        $this->assertSame('4.000', $destination->available_quantity);
        $this->assertSame('4.000', $destination->quantity_received);
        $this->assertSame($source->id, $transfer->source_batch_id);
        $this->assertSame($destination->id, $transfer->destination_batch_id);
        $this->assertDatabaseHas('stock_transactions', ['stock_batch_id' => $source->id, 'type' => 'transfer_out', 'reference_id' => $transfer->id]);
        $this->assertDatabaseHas('stock_transactions', ['stock_batch_id' => $destination->id, 'type' => 'transfer_in', 'reference_id' => $transfer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.transferred', 'auditable_id' => $transfer->id]);
    }

    public function test_adjustment_wastage_and_disposal_all_record_reasons_and_balances(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $batch = $this->batch($product, $centre, ['available_quantity' => '10.000', 'quantity_received' => '10.000']);

        foreach ([['adjustment_in', '2.000'], ['adjustment_out', '3.000'], ['wastage', '1.000'], ['disposal', '8.000']] as [$operation, $quantity]) {
            $this->actingAs($officer)->post(route('inventory.transactions.store'), $this->movementData($operation, $batch, $quantity))->assertRedirect();
        }

        $this->assertSame('0.000', $batch->fresh()->available_quantity);
        $this->assertSame('disposed', $batch->fresh()->status);
        $this->assertDatabaseHas('stock_transactions', ['type' => 'adjustment_in', 'quantity' => '2.000']);
        $this->assertDatabaseHas('stock_transactions', ['type' => 'adjustment_out', 'quantity' => '3.000']);
        $this->assertDatabaseHas('stock_transactions', ['type' => 'wastage', 'quantity' => '1.000']);
        $this->assertDatabaseHas('stock_transactions', ['type' => 'disposal', 'quantity' => '8.000']);
    }

    public function test_reorder_levels_generate_low_stock_and_expiry_alerts_and_batches_are_fefo_sorted(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $laterExpiry = $this->batch($product, $centre, ['batch_number' => 'LATER-LOT', 'available_quantity' => '3.000', 'expires_at' => today()->addMonths(6)->toDateString()]);
        $earlierExpiry = $this->batch($product, $centre, ['batch_number' => 'EARLIER-LOT', 'available_quantity' => '2.000', 'expires_at' => today()->addMonths(2)->toDateString()]);
        $expired = $this->batch($product, $centre, ['batch_number' => 'EXPIRED-LOT', 'available_quantity' => '1.000', 'expires_at' => today()->subDay()->toDateString()]);

        $this->actingAs($officer)->post(route('inventory.reorder-levels.store'), [
            'vaccine_product_id' => $product->id,
            'centre_id' => $centre->id,
            'minimum_quantity' => '10.000',
        ])->assertRedirect(route('inventory.reorder-levels.index'));
        $this->assertDatabaseHas('stock_reorder_levels', ['vaccine_product_id' => $product->id, 'centre_id' => $centre->id, 'minimum_quantity' => '10.000']);

        $this->actingAs($officer)->get(route('inventory.index'))->assertOk()
            ->assertSee('LOW STOCK')->assertSee('EXPIRED WITH BALANCE')->assertSee('FEFO first');
        $this->actingAs($officer)->get(route('inventory.index', ['state' => 'expired']))->assertOk()->assertSee('EXPIRED-LOT');
        $this->actingAs($officer)->get(route('inventory.index', ['state' => 'low_stock']))->assertOk()->assertSee('EARLIER-LOT');
        $this->actingAs($officer)->get(route('inventory.transactions.index'))->assertOk();

        $this->assertFalse($expired->fresh()->routineEligibleOn(today()));
        $this->assertSame(StockBatch::query()->where('batch_number', 'EARLIER-LOT')->value('id'), $earlierExpiry->id);
        $this->assertNotSame($laterExpiry->id, $earlierExpiry->id);
    }

    public function test_expired_batches_cannot_be_reactivated_or_routinely_issued(): void
    {
        $officer = $this->inventoryOfficer();
        [$product, $centre] = $this->productAndCentre();
        $batch = $this->batch($product, $centre, ['expires_at' => today()->subDay()->toDateString()]);

        $this->actingAs($officer)->from(route('inventory.index'))
            ->post(route('inventory.batches.status', $batch), ['status' => 'active', 'reason' => 'Attempt to release expired stock'])
            ->assertSessionHasErrors('status');
        $this->actingAs($officer)->from(route('inventory.transactions.create'))
            ->post(route('inventory.transactions.store'), $this->movementData('issue', $batch, '1.000'))
            ->assertSessionHasErrors('stock_batch_id');
        $this->assertSame('100.000', $batch->fresh()->available_quantity);
    }

    public function test_inventory_routes_are_forbidden_without_inventory_permission(): void
    {
        $user = $this->userWithPermissions(['children.view']);

        $this->actingAs($user)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($user)->get(route('inventory.transactions.index'))->assertForbidden();
    }

    /** @return array{VaccineProduct,Centre} */
    private function productAndCentre(): array
    {
        $vaccine = Vaccine::factory()->create();
        $product = VaccineProduct::factory()->for($vaccine)->create(['stock_unit' => 'dose', 'is_active' => true]);
        $centre = Centre::factory()->create(['is_active' => true]);

        return [$product, $centre];
    }

    private function batch(VaccineProduct $product, Centre $centre, array $overrides = []): StockBatch
    {
        return StockBatch::factory()->for($product, 'product')->for($centre)->create($overrides);
    }

    private function receiptData(VaccineProduct $product, Centre $centre): array
    {
        return [
            'vaccine_product_id' => $product->id,
            'centre_id' => $centre->id,
            'batch_number' => 'LOT-TEST-1',
            'supplier' => 'Authorized supplier',
            'received_at' => today()->toDateString(),
            'expires_at' => today()->addYear()->toDateString(),
            'quantity_received' => '100.000',
            'storage_location' => 'Cold room A',
        ];
    }

    private function movementData(string $operation, StockBatch $batch, string $quantity): array
    {
        return [
            'operation' => $operation,
            'stock_batch_id' => $batch->id,
            'quantity' => $quantity,
            'reason' => 'Approved inventory operation',
        ];
    }

    private function inventoryOfficer(): User
    {
        return $this->userWithPermissions(['inventory.manage']);
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
