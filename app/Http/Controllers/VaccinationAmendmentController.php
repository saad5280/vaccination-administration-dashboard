<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmendVaccinationRecordRequest;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\VaccinationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VaccinationAmendmentController extends Controller
{
    public function edit(Request $request, Child $child, VaccinationRecord $vaccinationRecord, VaccinationRecordController $records): View
    {
        abort_unless($vaccinationRecord->status === 'completed', 422, 'Only the current completed record can be amended.');
        AuditLog::record($request, 'vaccination.amendment_form_viewed', $vaccinationRecord);

        return view('vaccinations.amend', [
            ...$records->formData($child, $vaccinationRecord->load('stockBatch')),
            'record' => $vaccinationRecord,
        ]);
    }

    public function store(
        AmendVaccinationRecordRequest $request,
        Child $child,
        VaccinationRecord $vaccinationRecord,
        VaccinationRecordController $records,
    ): RedirectResponse {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $child, $vaccinationRecord, $records): void {
            $lockedChild = Child::query()->lockForUpdate()->findOrFail($child->id);
            $original = $lockedChild->vaccinationRecords()->lockForUpdate()->findOrFail($vaccinationRecord->id);
            abort_unless($original->status === 'completed', 422, 'Only the current completed record can be amended.');
            $records->ensureNoDuplicateDose($lockedChild, $data, $original->id);

            $oldIssue = StockTransaction::query()
                ->where('reference_type', VaccinationRecord::class)
                ->where('reference_id', $original->id)
                ->where('type', 'issue')
                ->lockForUpdate()
                ->first();

            if ($original->stock_batch_id !== null && ! $oldIssue) {
                throw ValidationException::withMessages(['stock_batch_id' => 'This record has no matching stock issue to reconcile. Ask an administrator to review the inventory history before correcting it.']);
            }

            $oldBatch = $oldIssue ? StockBatch::query()->lockForUpdate()->findOrFail($oldIssue->stock_batch_id) : null;
            $newBatch = StockBatch::query()->with('product')->lockForUpdate()->findOrFail($data['stock_batch_id']);
            $quantityToIssue = $newBatch->quantityForOneDose();

            if ($quantityToIssue <= 0) {
                throw ValidationException::withMessages(['stock_batch_id' => 'This vial configuration cannot be represented by the configured stock unit precision. Review the product setup.']);
            }

            $originalBefore = $records->auditValues($original);
            $returnQuantity = (float) ($oldIssue?->quantity ?? 0);

            if ($oldBatch && $returnQuantity > 0) {
                $oldBatch->increment('available_quantity', $returnQuantity);
            }

            if ((float) $newBatch->fresh()->available_quantity + 0.0001 < $quantityToIssue) {
                throw ValidationException::withMessages(['stock_batch_id' => 'The replacement batch does not have enough available stock.']);
            }

            $replacement = $lockedChild->vaccinationRecords()->create($records->recordAttributes(
                $data,
                $newBatch,
                $original->administered_by ?? $request->user()->id,
                $request->user()->id,
                $original->id,
                $data['correction_reason'],
            ));

            if ($oldBatch && $returnQuantity > 0) {
                $records->recordStockMovement($replacement, $oldBatch, 'return', $returnQuantity, $request->user()->id, 'Stock restored during vaccination record correction: '.$data['correction_reason'], $oldIssue->id);
            }

            $newBatch->decrement('available_quantity', $quantityToIssue);
            $records->recordStockMovement($replacement, $newBatch, 'issue', $quantityToIssue, $request->user()->id, 'Replacement vaccination record: '.$data['correction_reason']);

            $original->update(['status' => 'superseded', 'correction_reason' => $data['correction_reason']]);
            AuditLog::record($request, 'vaccination.superseded', $original, $originalBefore, $records->auditValues($original->fresh()));
            AuditLog::record($request, 'vaccination.amended', $replacement, null, $records->auditValues($replacement));
        });

        return redirect()->route('children.vaccinations.index', $child)->with('status', 'Corrected record saved. The original is preserved and the stock movements were reconciled.');
    }
}
