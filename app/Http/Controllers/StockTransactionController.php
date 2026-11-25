<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMovementRequest;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockTransactionController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->boolean('_livewire_inner')) {
            return view('inventory.transactions.index');
        }
        $filters = $request->validate([
            'centre_id' => ['nullable', 'integer', 'exists:centres,id'],
            'type' => ['nullable', 'in:receipt,issue,return,transfer_in,transfer_out,adjustment_in,adjustment_out,wastage,disposal'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $transactions = StockTransaction::query()
            ->with(['stockBatch.product.vaccine:id,name', 'centre:id,name', 'recordedBy:id,name', 'relatedTransaction'])
            ->when($filters['centre_id'] ?? null, fn ($query, $centreId) => $query->where('centre_id', $centreId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('inventory.transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $today = CarbonImmutable::today();
        $batches = StockBatch::query()->with(['product.vaccine:id,name', 'centre:id,name'])
            ->where('available_quantity', '>', 0)
            ->orderByRaw("CASE WHEN status = 'active' AND expires_at >= ? THEN 0 ELSE 1 END", [$today->toDateString()])
            ->orderBy('expires_at')->orderBy('id')->get();
        $returnIssues = StockTransaction::query()->with(['stockBatch.product.vaccine:id,name', 'centre:id,name', 'returns'])
            ->where('type', 'issue')->where('reference_type', 'inventory_issue')->orderByDesc('occurred_at')->get()
            ->filter(fn (StockTransaction $issue): bool => (int) round(((float) $issue->quantity - (float) $issue->returns->sum('quantity')) * 1000) > 0)
            ->values();

        return view('inventory.transactions.create', [
            'batches' => $batches,
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'returnIssues' => $returnIssues,
            'today' => $today,
        ]);
    }

    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->applyMovement($data, $request);

        return redirect()->route('inventory.transactions.index')->with('status', 'Inventory movement recorded and audited.');
    }

    /** @param array<string, mixed> $data */
    private function applyMovement(array $data, Request $request): void
    {
        DB::transaction(function () use ($data, $request): void {
            $batch = StockBatch::query()->with('product')->lockForUpdate()->findOrFail($data['stock_batch_id']);
            $operation = $data['operation'];
            $quantityMilli = (int) round((float) $data['quantity'] * 1000);
            $availableMilli = (int) round((float) $batch->available_quantity * 1000);
            $today = CarbonImmutable::today();

            if ($batch->status === 'disposed') {
                throw ValidationException::withMessages(['stock_batch_id' => 'Disposed batches cannot be changed.']);
            }

            if (in_array($operation, ['issue', 'transfer', 'adjustment_in', 'adjustment_out'], true) && ! $batch->routineEligibleOn($today)) {
                throw ValidationException::withMessages(['stock_batch_id' => 'Expired, quarantined, or disposed batches cannot be used for this operation.']);
            }

            if (in_array($operation, ['issue', 'transfer', 'adjustment_out', 'wastage', 'disposal'], true) && $quantityMilli > $availableMilli) {
                throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds available stock.']);
            }

            if ($operation === 'return') {
                $this->returnToBatch($data, $request, $batch, $quantityMilli);

                return;
            }

            if ($operation === 'transfer') {
                $this->transfer($data, $request, $batch, $quantityMilli);

                return;
            }

            $before = $this->batchValues($batch);
            if (in_array($operation, ['issue', 'adjustment_out', 'wastage', 'disposal'], true)) {
                $batch->decrement('available_quantity', $quantityMilli / 1000);
            } else {
                $batch->increment('available_quantity', $quantityMilli / 1000);
            }

            if ($operation === 'disposal' && (int) round((float) $batch->fresh()->available_quantity * 1000) === 0) {
                $batch->update(['status' => 'disposed']);
            }

            $type = $operation;
            $transaction = $this->createTransaction($batch, $type, $quantityMilli, $data['reason'], $request->user()->id, $operation === 'issue' ? 'inventory_issue' : 'inventory_operation');
            AuditLog::record($request, 'stock.'.$operation, $transaction, $before, [
                ...$this->batchValues($batch->fresh()), 'quantity' => number_format($quantityMilli / 1000, 3, '.', ''), 'reason' => $data['reason'],
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    private function returnToBatch(array $data, Request $request, StockBatch $batch, int $quantityMilli): void
    {
        $issue = StockTransaction::query()->with('returns')->lockForUpdate()->findOrFail($data['related_transaction_id']);
        $returnedMilli = (int) round((float) $issue->returns->sum('quantity') * 1000);
        $issueMilli = (int) round((float) $issue->quantity * 1000);
        if ($issue->type !== 'issue' || $issue->reference_type !== 'inventory_issue' || $issue->stock_batch_id !== $batch->id || $quantityMilli > $issueMilli - $returnedMilli) {
            throw ValidationException::withMessages(['related_transaction_id' => 'The selected manual issue no longer has enough quantity available for return.']);
        }

        $before = $this->batchValues($batch);
        $batch->increment('available_quantity', $quantityMilli / 1000);
        $transaction = $this->createTransaction($batch, 'return', $quantityMilli, $data['reason'], $request->user()->id, 'inventory_return', null, $issue->id);
        AuditLog::record($request, 'stock.return', $transaction, $before, [
            ...$this->batchValues($batch->fresh()), 'quantity' => number_format($quantityMilli / 1000, 3, '.', ''),
            'reason' => $data['reason'], 'related_transaction_id' => $issue->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function transfer(array $data, Request $request, StockBatch $source, int $quantityMilli): void
    {
        $destinationCentre = Centre::query()->lockForUpdate()->findOrFail($data['destination_centre_id']);
        $destination = StockBatch::query()->where('vaccine_product_id', $source->vaccine_product_id)
            ->where('centre_id', $destinationCentre->id)->where('batch_number', $source->batch_number)
            ->lockForUpdate()->first();

        if ($destination && ($destination->status !== 'active'
            || $destination->expires_at->toDateString() !== $source->expires_at->toDateString()
            || ! $destination->routineEligibleOn(CarbonImmutable::today()))) {
            throw ValidationException::withMessages(['destination_centre_id' => 'The destination has a matching lot that is expired or quarantined. Review the batch before transferring.']);
        }

        if (! $destination) {
            $destination = StockBatch::query()->create([
                'vaccine_product_id' => $source->vaccine_product_id,
                'centre_id' => $destinationCentre->id,
                'batch_number' => $source->batch_number,
                'supplier' => $source->supplier,
                'received_at' => CarbonImmutable::today()->toDateString(),
                'expires_at' => $source->expires_at,
                'quantity_received' => '0.000',
                'available_quantity' => '0.000',
                'storage_location' => $source->storage_location,
                'status' => 'active',
            ]);
        }

        $sourceBefore = $this->batchValues($source);
        $destinationBefore = $this->batchValues($destination);
        $source->decrement('available_quantity', $quantityMilli / 1000);
        $destination->increment('quantity_received', $quantityMilli / 1000);
        $destination->increment('available_quantity', $quantityMilli / 1000);
        $transfer = StockTransfer::query()->create([
            'source_batch_id' => $source->id,
            'destination_batch_id' => $destination->id,
            'quantity' => number_format($quantityMilli / 1000, 3, '.', ''),
            'reason' => $data['reason'],
            'recorded_by' => $request->user()->id,
            'transferred_at' => now(),
        ]);
        $this->createTransaction($source, 'transfer_out', $quantityMilli, $data['reason'], $request->user()->id, StockTransfer::class, $transfer->id);
        $this->createTransaction($destination, 'transfer_in', $quantityMilli, $data['reason'], $request->user()->id, StockTransfer::class, $transfer->id);
        AuditLog::record($request, 'stock.transferred', $transfer, $sourceBefore, [
            'source' => $sourceBefore,
            'source_after' => $this->batchValues($source->fresh()),
            'destination_before' => $destinationBefore,
            'destination_after' => $this->batchValues($destination->fresh()),
            'quantity' => number_format($quantityMilli / 1000, 3, '.', ''),
            'reason' => $data['reason'],
        ]);
    }

    private function createTransaction(StockBatch $batch, string $type, int $quantityMilli, string $reason, int $recordedBy, string $referenceType, ?int $referenceId = null, ?int $relatedTransactionId = null): StockTransaction
    {
        return StockTransaction::query()->create([
            'stock_batch_id' => $batch->id,
            'centre_id' => $batch->centre_id,
            'type' => $type,
            'quantity' => number_format($quantityMilli / 1000, 3, '.', ''),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'related_transaction_id' => $relatedTransactionId,
            'reason' => $reason,
            'recorded_by' => $recordedBy,
            'occurred_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function batchValues(StockBatch $batch): array
    {
        return $batch->only(['vaccine_product_id', 'centre_id', 'batch_number', 'expires_at', 'available_quantity', 'status']);
    }
}
