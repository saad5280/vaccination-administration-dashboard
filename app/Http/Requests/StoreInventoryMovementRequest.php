<?php

namespace App\Http\Requests;

use App\Models\StockBatch;
use App\Models\StockTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['issue', 'return', 'transfer', 'adjustment_in', 'adjustment_out', 'wastage', 'disposal'])],
            'stock_batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'destination_centre_id' => ['nullable', 'required_if:operation,transfer', 'integer', Rule::exists('centres', 'id')->where('is_active', true)],
            'related_transaction_id' => ['nullable', 'required_if:operation,return', 'integer', 'exists:stock_transactions,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d+(\.\d{1,3})?$/'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['operation', 'stock_batch_id', 'destination_centre_id', 'related_transaction_id', 'quantity'])) {
                return;
            }

            $operation = $this->input('operation');
            $batch = StockBatch::query()->find($this->integer('stock_batch_id'));
            $quantityMilli = (int) round((float) $this->input('quantity') * 1000);
            $availableMilli = (int) round((float) $batch->available_quantity * 1000);
            if ($batch->status === 'disposed') {
                $validator->errors()->add('stock_batch_id', 'Disposed batches cannot be changed.');

                return;
            }

            $routineOperation = in_array($operation, ['issue', 'transfer', 'adjustment_in', 'adjustment_out'], true);
            if ($routineOperation && ! $batch->routineEligibleOn(CarbonImmutable::today())) {
                $validator->errors()->add('stock_batch_id', 'Expired, quarantined, or disposed batches cannot be used for this operation.');
            }

            if ($operation === 'transfer' && (int) $this->input('destination_centre_id') === $batch->centre_id) {
                $validator->errors()->add('destination_centre_id', 'Choose a centre different from the source centre.');
            }

            if (in_array($operation, ['issue', 'transfer', 'adjustment_out', 'wastage', 'disposal'], true) && $quantityMilli > $availableMilli) {
                $validator->errors()->add('quantity', 'The requested quantity exceeds available stock.');
            }

            if ($operation === 'return') {
                $issue = StockTransaction::query()->with('returns')->find($this->integer('related_transaction_id'));

                if (! $issue || $issue->type !== 'issue' || $issue->reference_type !== 'inventory_issue' || $issue->stock_batch_id !== $batch->id) {
                    $validator->errors()->add('related_transaction_id', 'Choose an unreturned manual issue for this batch.');
                } elseif ($quantityMilli > (int) round(((float) $issue->quantity - (float) $issue->returns->sum('quantity')) * 1000)) {
                    $validator->errors()->add('quantity', 'The return quantity exceeds the remaining quantity from the selected issue.');
                }
            }
        }];
    }
}
