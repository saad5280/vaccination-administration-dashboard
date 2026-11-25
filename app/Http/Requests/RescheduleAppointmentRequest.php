<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\ScheduleDose;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('appointments.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'centre_id' => ['required', 'integer', Rule::exists('centres', 'id')->where('is_active', true)],
            'vaccinator_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['scheduled_at', 'centre_id', 'vaccinator_id'])) {
                return;
            }

            /** @var Appointment $appointment */
            $appointment = $this->route('appointment');
            $scheduledDate = CarbonImmutable::parse($this->input('scheduled_at'))->toDateString();

            if ($appointment->schedule_dose_id) {
                $dose = ScheduleDose::query()->with('schedule')->find($appointment->schedule_dose_id);

                if (! $dose || $dose->schedule->approval_status !== 'approved'
                    || ! $dose->schedule->effective_from
                    || $dose->schedule->effective_from->toDateString() > $scheduledDate
                    || ($dose->schedule->effective_until && $dose->schedule->effective_until->toDateString() < $scheduledDate)) {
                    $validator->errors()->add('scheduled_at', 'The configured schedule dose is not effective on the new appointment date.');
                }
            }

            if ($this->filled('vaccinator_id') && ! User::query()->find($this->integer('vaccinator_id'))?->hasPermission('vaccinations.record')) {
                $validator->errors()->add('vaccinator_id', 'Choose an active user authorized to administer vaccinations.');
            }

            if ($this->user()->isCentreScoped() && ! $this->user()->centres()->whereKey($this->integer('centre_id'))->exists()) {
                $validator->errors()->add('centre_id', 'Choose one of your assigned vaccination centres.');
            }
        }];
    }
}
