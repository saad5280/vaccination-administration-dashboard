<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVaccineProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('catalogue.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'manufacturer' => ['nullable', 'string', 'max:255', 'required_without:product_name'],
            'product_name' => ['nullable', 'string', 'max:255', 'required_without:manufacturer'],
            'presentation' => ['nullable', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:50'],
            'storage_requirements' => ['nullable', 'string', 'max:255'],
            'doses_per_vial' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'stock_unit' => ['required', 'string', 'max:30'],
            'dose_quantity' => ['nullable', 'numeric', 'gt:0'],
            'dose_unit' => ['nullable', 'string', 'max:30', 'required_with:dose_quantity'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
