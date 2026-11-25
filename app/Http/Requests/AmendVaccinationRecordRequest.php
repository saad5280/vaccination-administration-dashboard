<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class AmendVaccinationRecordRequest extends StoreVaccinationRecordRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('vaccinations.amend') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'correction_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return parent::after();
    }
}
