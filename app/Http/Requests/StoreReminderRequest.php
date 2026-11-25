<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\ReminderTemplate;
use App\Models\ScheduleDose;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reminders.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', Rule::exists('children', 'id')->where('status', 'active')],
            'guardian_id' => ['nullable', 'integer', 'exists:guardians,id'],
            'schedule_dose_id' => ['nullable', 'integer', 'exists:schedule_doses,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'template_id' => ['nullable', 'integer', Rule::exists('reminder_templates', 'id')->where('is_active', true)->where('channel', 'in_app')],
            'scheduled_for' => ['required', 'date', 'after_or_equal:now'],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['child_id', 'guardian_id', 'appointment_id', 'schedule_dose_id', 'template_id'])) {
                return;
            }

            $childId = $this->integer('child_id');
            $child = Child::query()->find($childId);
            if ($child && ! $this->user()->canAccessChild($child)) {
                $validator->errors()->add('child_id', 'Choose a child assigned to one of your vaccination centres.');
            }

            if ($this->filled('guardian_id')) {
                $guardian = Guardian::query()->whereHas('children', fn ($query) => $query->where('children.id', $childId))->find($this->integer('guardian_id'));

                if (! $guardian) {
                    $validator->errors()->add('guardian_id', 'Choose a guardian linked to this child.');
                } elseif ($guardian->children()->whereKey($childId)->first()?->pivot->consent_status === 'declined') {
                    $validator->errors()->add('guardian_id', 'This guardian declined contact consent; leave the guardian unset and verify consent through the approved process.');
                }
            }

            if ($this->filled('appointment_id') && Appointment::query()->whereKey($this->integer('appointment_id'))->where('child_id', $childId)->doesntExist()) {
                $validator->errors()->add('appointment_id', 'Choose an appointment for this child.');
            }

            if ($this->filled('appointment_id') && $this->filled('schedule_dose_id')) {
                $appointment = Appointment::query()->find($this->integer('appointment_id'));

                if ($appointment?->schedule_dose_id && $appointment->schedule_dose_id !== $this->integer('schedule_dose_id')) {
                    $validator->errors()->add('schedule_dose_id', 'The selected dose does not match the related appointment.');
                }
            }

            if ($this->filled('schedule_dose_id') && ! ScheduleDose::query()->whereKey($this->integer('schedule_dose_id'))->exists()) {
                $validator->errors()->add('schedule_dose_id', 'Choose a configured schedule dose.');
            }

            if ($this->filled('template_id') && ReminderTemplate::query()->whereKey($this->integer('template_id'))->where('channel', '!=', 'in_app')->exists()) {
                $validator->errors()->add('template_id', 'External message templates cannot be used until a delivery provider is configured.');
            }

            if ($this->filled('scheduled_for') && CarbonImmutable::parse($this->input('scheduled_for'))->isPast()) {
                $validator->errors()->add('scheduled_for', 'The reminder task date cannot be in the past.');
            }
        }];
    }
}
