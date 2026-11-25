<?php

namespace App\Http\Requests;

use App\Models\Vaccine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVaccineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => mb_strtoupper(trim($this->string('code')->toString()))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('catalogue.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vaccine $vaccine */
        $vaccine = $this->route('vaccine');

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('vaccines', 'code')->ignore($vaccine->id)],
            'name' => ['required', 'string', 'max:255'],
            'target_disease' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
