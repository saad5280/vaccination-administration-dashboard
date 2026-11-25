<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reminders.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['completed', 'failed'])],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
            'failure_reason' => ['nullable', 'required_if:status,failed', 'string', 'min:3', 'max:500'],
        ];
    }
}
