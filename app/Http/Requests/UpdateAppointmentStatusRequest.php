<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('appointments.manage') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['attended', 'missed', 'cancelled'])],
            'cancellation_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Appointment $appointment */
            $appointment = $this->route('appointment');

            if ($appointment->status !== 'booked') {
                $validator->errors()->add('status', 'Only a booked appointment can be marked attended, missed, or cancelled.');
            }

            if ($this->input('status') === 'missed' && $appointment->scheduled_at->isFuture()) {
                $validator->errors()->add('status', 'An appointment cannot be marked missed before its scheduled time.');
            }

            if ($this->input('status') === 'attended' && $appointment->scheduled_at->isFuture()) {
                $validator->errors()->add('status', 'An appointment cannot be marked attended before its scheduled time.');
            }
        }];
    }
}
