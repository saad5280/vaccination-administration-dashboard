<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReminderRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reminders.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('reminder_rules', 'name')],
            'trigger_type' => ['required', Rule::in(['before_due', 'on_due', 'after_missed_appointment'])],
            'offset_days' => ['required', 'integer', 'min:0', 'max:365'],
            'template_name' => ['required', 'string', 'max:120'],
            'template_body' => ['required', 'string', 'min:3', 'max:2000'],
            'language' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('trigger_type') === 'on_due' && (int) $this->input('offset_days') !== 0) {
                $validator->errors()->add('offset_days', 'On-due reminders must use a zero-day offset.');
            }

            if (in_array($this->input('trigger_type'), ['before_due', 'after_missed_appointment'], true)
                && $this->input('trigger_type') === 'before_due'
                && (int) $this->input('offset_days') < 1) {
                $validator->errors()->add('offset_days', 'A before-due reminder needs an offset of at least one day.');
            }
        }];
    }
}
