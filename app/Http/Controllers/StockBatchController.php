<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockReceiptRequest;
use App\Http\Requests\UpdateStockBatchStatusRequest;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\VaccineProduct;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockBatchController extends Controller
{
    public function create(): View
    {
        return view('inventory.receipts.create', [
            'products' => VaccineProduct::query()->with('vaccine:id,name')->where('is_active', true)->orderBy('vaccine_id')->orderBy('product_name')->get(),
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreStockReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batch = DB::transaction(function () use ($data, $request): StockBatch {
            VaccineProduct::query()->lockForUpdate()->findOrFail($data['vaccine_product_id']);
            Centre::query()->lockForUpdate()->findOrFail($data['centre_id']);

            if (StockBatch::query()->where('vaccine_product_id', $data['vaccine_product_id'])
                ->where('centre_id', $data['centre_id'])->where('batch_number', $data['batch_number'])->exists()) {
                throw ValidationException::withMessages(['batch_number' => 'A batch with this product and lot number already exists at this centre.']);
            }

            $quantity = number_format((float) $data['quantity_received'], 3, '.', '');
            $batch = StockBatch::query()->create([
                'vaccine_product_id' => $data['vaccine_product_id'],
                'centre_id' => $data['centre_id'],
                'batch_number' => $data['batch_number'],
                'supplier' => $data['supplier'] ?? null,
                'received_at' => $data['received_at'],
                'expires_at' => $data['expires_at'],
                'quantity_received' => $quantity,
                'available_quantity' => $quantity,
                'storage_location' => $data['storage_location'] ?? null,
                'status' => 'active',
            ]);

            StockTransaction::query()->create([
                'stock_batch_id' => $batch->id,
                'centre_id' => $batch->centre_id,
                'type' => 'receipt',
                'quantity' => $quantity,
                'reference_type' => StockBatch::class,
                'reference_id' => $batch->id,
                'reason' => 'Initial stock receipt',
                'recorded_by' => $request->user()->id,
                'occurred_at' => CarbonImmutable::parse($data['received_at'])->startOfDay(),
            ]);

            AuditLog::record($request, 'stock.received', $batch, null, $this->auditValues($batch));

            return $batch;
        });

        return redirect()->route('inventory.index')->with('status', "Received {$batch->batch_number} into stock.");
    }

    public function updateStatus(UpdateStockBatchStatusRequest $request, StockBatch $stockBatch): RedirectResponse
    {
        DB::transaction(function () use ($request, $stockBatch): void {
            $batch = StockBatch::query()->lockForUpdate()->findOrFail($stockBatch->id);
            $status = $request->validated('status');

            abort_if($batch->status === 'disposed', 422, 'Disposed batches cannot be reactivated.');
            if ($status === 'active' && $batch->expires_at->toDateString() < CarbonImmutable::today()->toDateString()) {
                throw ValidationException::withMessages(['status' => 'Expired batches cannot be reactivated.']);
            }

            $before = $this->auditValues($batch);
            $batch->update(['status' => $status]);
            AuditLog::record($request, 'stock.batch_status_changed', $batch, $before, [
                ...$this->auditValues($batch->fresh()), 'reason' => $request->validated('reason'),
            ]);
        });

        return redirect()->route('inventory.index')->with('status', 'Batch status updated.');
    }

    /** @return array<string, mixed> */
    private function auditValues(StockBatch $batch): array
    {
        return $batch->only(['vaccine_product_id', 'centre_id', 'batch_number', 'expires_at', 'quantity_received', 'available_quantity', 'status']);
    }
}
