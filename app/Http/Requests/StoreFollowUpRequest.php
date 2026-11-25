<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Child;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reminders.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'appointment_id' => ['nullable', 'required_if:outcome,appointment_booked', 'integer', 'exists:appointments,id'],
            'schedule_dose_id' => ['nullable', 'integer', 'exists:schedule_doses,id'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'outcome' => ['required', Rule::in(['contacted', 'appointment_booked', 'visited', 'vaccinated', 'unavailable', 'moved_transferred', 'follow_up_required'])],
            'contacted_at' => ['nullable', 'date', 'before_or_equal:now', 'required_if:outcome,contacted'],
            'next_action_at' => ['nullable', 'required_if:outcome,follow_up_required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['child_id', 'appointment_id'])) {
                return;
            }

            if ($this->filled('appointment_id') && ! Appointment::query()
                ->whereKey($this->integer('appointment_id'))
                ->where('child_id', $this->integer('child_id'))
                ->exists()) {
                $validator->errors()->add('appointment_id', 'Choose an appointment for this child.');
            }

            if ($this->filled('assigned_to') && ! User::query()->find($this->integer('assigned_to'))?->hasPermission('children.view')) {
                $validator->errors()->add('assigned_to', 'Choose an active staff member with child-record access.');
            }

            $child = Child::query()->find($this->integer('child_id'));
            if ($child && ! $this->user()->canAccessChild($child)) {
                $validator->errors()->add('child_id', 'Choose a child assigned to one of your vaccination centres.');
            }
        }];
    }
}
