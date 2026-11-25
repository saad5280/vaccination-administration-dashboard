<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'vaccine_product_id' => ['required', 'integer', Rule::exists('vaccine_products', 'id')->where('is_active', true)],
            'centre_id' => ['required', 'integer', Rule::exists('centres', 'id')->where('is_active', true)],
            'batch_number' => ['required', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'expires_at' => ['required', 'date', 'after:received_at'],
            'quantity_received' => ['required', 'numeric', 'gt:0', 'max:999999999.999', 'regex:/^\d+(\.\d{1,3})?$/'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['vaccine_product_id', 'centre_id', 'batch_number', 'received_at', 'expires_at'])) {
                return;
            }

            if (CarbonImmutable::parse($this->input('expires_at'))->startOfDay()->lte(CarbonImmutable::today())) {
                $validator->errors()->add('expires_at', 'Newly received stock must be in date at receipt.');
            }

            if (DB::table('stock_batches')
                ->where('vaccine_product_id', $this->integer('vaccine_product_id'))
                ->where('centre_id', $this->integer('centre_id'))
                ->where('batch_number', $this->input('batch_number'))
                ->exists()) {
                $validator->errors()->add('batch_number', 'A batch with this product and lot number already exists at this centre.');
            }
        }];
    }
}
