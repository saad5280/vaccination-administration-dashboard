<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use App\Models\ScheduleDose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('schedules.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Schedule $schedule */
        $schedule = $this->route('schedule');
        /** @var ScheduleDose $dose */
        $dose = $this->route('dose');

        return [
            'vaccine_id' => ['required', 'integer', Rule::exists('vaccines', 'id')->where('is_active', true)],
            'dose_number' => ['required', 'integer', 'min:1', 'max:65535', Rule::unique('schedule_doses', 'dose_number')->where('schedule_id', $schedule->id)->where('vaccine_id', $this->input('vaccine_id'))->ignore($dose->id)],
            'recommended_age_days' => ['nullable', 'integer', 'min:0'],
            'minimum_age_days' => ['nullable', 'integer', 'min:0'],
            'interval_from_previous_days' => ['nullable', 'integer', 'min:0'],
            'minimum_interval_days' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
