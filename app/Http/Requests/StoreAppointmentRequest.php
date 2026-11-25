<?php

namespace App\Http\Requests;

use App\Models\Child;
use App\Models\ScheduleDose;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('appointments.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', Rule::exists('children', 'id')->where('status', 'active')],
            'schedule_dose_id' => ['nullable', 'integer', 'exists:schedule_doses,id'],
            'centre_id' => ['required', 'integer', Rule::exists('centres', 'id')->where('is_active', true)],
            'vaccinator_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['schedule_dose_id', 'scheduled_at', 'vaccinator_id'])) {
                return;
            }

            $appointmentDate = CarbonImmutable::parse($this->input('scheduled_at'))->toDateString();

            if ($this->filled('schedule_dose_id')) {
                $dose = ScheduleDose::query()->with('schedule')->find($this->integer('schedule_dose_id'));

                if (! $dose || $dose->schedule->approval_status !== 'approved'
                    || ! $dose->schedule->effective_from
                    || $dose->schedule->effective_from->toDateString() > $appointmentDate
                    || ($dose->schedule->effective_until && $dose->schedule->effective_until->toDateString() < $appointmentDate)) {
                    $validator->errors()->add('schedule_dose_id', 'Choose a dose from a schedule version approved and effective on the appointment date.');
                }
            }

            if ($this->filled('vaccinator_id')) {
                $vaccinator = $this->user()->newQuery()->find($this->integer('vaccinator_id'));

                if (! $vaccinator?->hasPermission('vaccinations.record')) {
                    $validator->errors()->add('vaccinator_id', 'Choose an active user authorized to administer vaccinations.');
                }
            }

            $child = Child::query()->find($this->integer('child_id'));
            if ($child && ! $this->user()->canAccessChild($child)) {
                $validator->errors()->add('child_id', 'Choose a child assigned to one of your vaccination centres.');
            }

            if ($this->user()->isCentreScoped() && ! $this->user()->centres()->whereKey($this->integer('centre_id'))->exists()) {
                $validator->errors()->add('centre_id', 'Choose one of your assigned vaccination centres.');
            }
        }];
    }
}
