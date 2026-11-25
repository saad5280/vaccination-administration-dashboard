<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:40', Rule::unique('schedules', 'version')->where('name', $this->input('name'))->ignore($schedule->id)],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
