<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildDoseDirectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('vaccinations.clinical_authorize') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'schedule_dose_id' => ['required', 'integer', 'exists:schedule_doses,id'],
            'status' => ['required', Rule::in(['active', 'deferred', 'contraindicated', 'not_eligible'])],
            'due_date_override' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
