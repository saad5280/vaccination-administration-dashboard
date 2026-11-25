<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStockReorderLevelRequest extends FormRequest
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
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(\.\d{1,3})?$/'],
        ];
    }
}
