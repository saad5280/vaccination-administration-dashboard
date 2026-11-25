<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaccinationRecordRequest;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VaccinationRecordController extends Controller
{
    public function index(Request $request, Child $child): View
    {
        AuditLog::record($request, 'vaccination.history_viewed', $child);

        return view('vaccinations.index', [
            'child' => $child,
            'records' => $child->vaccinationRecords()
                ->with(['administeredBy:id,name', 'centre:id,name', 'scheduleDose.schedule:id,name,version', 'stockBatch:id,batch_number,expires_at', 'vaccine:id,code,name', 'replacement:id,supersedes_record_id'])
                ->orderByDesc('administered_at')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request, Child $child): View
    {
        AuditLog::record($request, 'vaccination.entry_form_viewed', $child);

        return view('vaccinations.create', $this->formData($child, null));
    }

    public function store(StoreVaccinationRecordRequest $request, Child $child): RedirectResponse
    {
        $data = $request->validated();

        $record = DB::transaction(function () use ($data, $request, $child): VaccinationRecord {
            $lockedChild = Child::query()->lockForUpdate()->findOrFail($child->id);
            $batch = StockBatch::query()->with('product')->lockForUpdate()->findOrFail($data['stock_batch_id']);
            $this->ensureNoDuplicateDose($lockedChild, $data);
            $quantityToIssue = $batch->quantityForOneDose();

            if ($quantityToIssue <= 0) {
                throw ValidationException::withMessages(['stock_batch_id' => 'This vial configuration cannot be represented by the configured stock unit precision. Review the product setup.']);
            }

            if ((float) $batch->available_quantity + 0.0001 < $quantityToIssue) {
                throw ValidationException::withMessages(['stock_batch_id' => 'This batch does not have enough available stock for another dose.']);
            }

            $record = $lockedChild->vaccinationRecords()->create($this->recordAttributes(
                $data,
                $batch,
                $request->user()->id,
                $request->user()->id,
            ));

            $batch->decrement('available_quantity', $quantityToIssue);
            $this->recordStockMovement($record, $batch, 'issue', $quantityToIssue, $request->user()->id, 'Vaccine dose administered.');
            AuditLog::record($request, 'vaccination.recorded', $record, null, $this->auditValues($record));

            return $record;
        });

        return redirect()->route('children.vaccinations.index', $child)->with('status', 'Vaccination record saved and the selected batch stock updated.');
    }

    /** @return array{child: Child, record: ?VaccinationRecord, batches: Collection, vaccines: Collection, scheduleDoses: Collection, centres: Collection} */
    public function formData(Child $child, ?VaccinationRecord $record): array
    {
        return [
            'child' => $child,
            'record' => $record,
            'batches' => StockBatch::query()
                ->with(['centre:id,name', 'product:id,vaccine_id,product_name,manufacturer,stock_unit,dose_quantity,dose_unit,route,doses_per_vial', 'product.vaccine:id,code,name'])
                ->where('status', 'active')
                ->where('available_quantity', '>', 0)
                ->whereDate('expires_at', '>=', today())
                ->orderBy('expires_at')
                ->orderBy('id')
                ->get(),
            'vaccines' => Vaccine::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'scheduleDoses' => ScheduleDose::query()
                ->with(['vaccine:id,code,name', 'schedule:id,name,version,effective_from,effective_until'])
                ->whereHas('schedule', fn (Builder $query) => $query->where('approval_status', 'approved'))
                ->orderBy('vaccine_id')
                ->orderBy('dose_number')
                ->get(),
            'centres' => Centre::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function ensureNoDuplicateDose(Child $child, array $data, ?int $ignoreRecordId = null): void
    {
        $query = $child->vaccinationRecords()
            ->where('status', 'completed')
            ->whereDate('administered_at', substr($data['administered_at'], 0, 10));

        if ($ignoreRecordId) {
            $query->whereKeyNot($ignoreRecordId);
        }

        if (filled($data['schedule_dose_id'] ?? null)) {
            $query->where('schedule_dose_id', $data['schedule_dose_id']);
        } else {
            $query->where('vaccine_id', $data['vaccine_id'])
                ->where('dose_number', $data['dose_number']);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['dose_number' => 'A completed record for this dose already exists on that date. Review the child’s history before proceeding.']);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordAttributes(array $data, StockBatch $batch, int $administeredBy, int $createdBy, ?int $supersedesId = null, ?string $correctionReason = null): array
    {
        return [
            'vaccine_id' => $data['vaccine_id'],
            'schedule_dose_id' => $data['schedule_dose_id'] ?? null,
            'dose_number' => $data['dose_number'],
            'administered_at' => $data['administered_at'],
            'stock_batch_id' => $batch->id,
            'lot_number' => $batch->batch_number,
            'batch_expiry_date' => $batch->expires_at,
            'manufacturer' => $batch->product->manufacturer,
            'product_name' => $batch->product->product_name,
            'dose_quantity' => $data['dose_quantity'] ?? $batch->product->dose_quantity,
            'dose_unit' => $data['dose_unit'] ?? $batch->product->dose_unit,
            'route' => $data['route'] ?? $batch->product->route,
            'administration_site' => $data['administration_site'] ?? null,
            'centre_id' => $batch->centre_id,
            'administered_by' => $administeredBy,
            'status' => 'completed',
            'notes' => $data['notes'] ?? null,
            'supersedes_record_id' => $supersedesId,
            'correction_reason' => $correctionReason,
            'created_by' => $createdBy,
        ];
    }

    /** @return array<string, mixed> */
    public function auditValues(VaccinationRecord $record): array
    {
        return $record->only([
            'child_id', 'vaccine_id', 'schedule_dose_id', 'dose_number', 'administered_at',
            'stock_batch_id', 'lot_number', 'batch_expiry_date', 'manufacturer', 'product_name',
            'dose_quantity', 'dose_unit', 'route', 'administration_site', 'centre_id',
            'administered_by', 'status', 'supersedes_record_id', 'correction_reason',
        ]);
    }

    public function recordStockMovement(VaccinationRecord $record, StockBatch $batch, string $type, float|string $quantity, int $recordedBy, string $reason, ?int $relatedTransactionId = null): void
    {
        StockTransaction::query()->create([
            'stock_batch_id' => $batch->id,
            'centre_id' => $batch->centre_id,
            'type' => $type,
            'quantity' => number_format((float) $quantity, 3, '.', ''),
            'reference_type' => VaccinationRecord::class,
            'reference_id' => $record->id,
            'related_transaction_id' => $relatedTransactionId,
            'reason' => $reason,
            'recorded_by' => $recordedBy,
            'occurred_at' => $record->administered_at,
        ]);
    }
}
