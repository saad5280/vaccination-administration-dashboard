<?php

namespace App\Http\Requests;

use App\Models\Child;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\VaccinationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVaccinationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('vaccinations.record') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vaccine_id' => ['required', 'integer', 'exists:vaccines,id'],
            'schedule_dose_id' => ['nullable', 'integer', 'exists:schedule_doses,id'],
            'dose_number' => ['required', 'integer', 'min:1', 'max:65535'],
            'administered_at' => ['required', 'date', 'before_or_equal:now'],
            'stock_batch_id' => ['required', 'integer', Rule::exists('stock_batches', 'id')->where('status', 'active')],
            'dose_quantity' => ['nullable', 'numeric', 'gt:0'],
            'dose_unit' => ['nullable', 'string', 'max:30', 'required_with:dose_quantity'],
            'route' => ['nullable', 'string', 'max:50'],
            'administration_site' => ['nullable', 'string', 'max:80'],
            'centre_id' => ['required', 'integer', Rule::exists('centres', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['vaccine_id', 'schedule_dose_id', 'dose_number', 'administered_at', 'stock_batch_id', 'centre_id'])) {
                return;
            }

            /** @var Child $child */
            $child = $this->route('child');
            $administrationTime = CarbonImmutable::parse($this->input('administered_at'));
            $scheduleDose = $this->filled('schedule_dose_id')
                ? ScheduleDose::query()->with('schedule')->find($this->integer('schedule_dose_id'))
                : null;
            $batch = StockBatch::query()->with('product')->find($this->integer('stock_batch_id'));

            if ($administrationTime->startOfDay()->lt(CarbonImmutable::parse($child->date_of_birth)->startOfDay())) {
                $validator->errors()->add('administered_at', 'The administration date cannot be before the child’s date of birth.');
            }

            if ($batch && $batch->product->vaccine_id !== $this->integer('vaccine_id')) {
                $validator->errors()->add('stock_batch_id', 'Choose a batch for the selected vaccine.');
            }

            if ($batch && $batch->centre_id !== $this->integer('centre_id')) {
                $validator->errors()->add('stock_batch_id', 'Choose a batch held at the selected vaccination centre.');
            }

            if ($batch && $batch->expires_at->lt($administrationTime->startOfDay())) {
                $validator->errors()->add('stock_batch_id', 'The selected batch was expired on the administration date.');
            }

            if ($batch && blank($this->input('dose_quantity')) && blank($batch->product->dose_quantity)) {
                $validator->errors()->add('dose_quantity', 'Enter the administered quantity or configure a quantity on the product presentation.');
            }

            if ($batch && blank($this->input('dose_unit')) && blank($batch->product->dose_unit)) {
                $validator->errors()->add('dose_unit', 'Enter the administered unit or configure a unit on the product presentation.');
            }

            if ($batch && blank($this->input('route')) && blank($batch->product->route)) {
                $validator->errors()->add('route', 'Enter the administration route or configure a route on the product presentation.');
            }

            if ($this->filled('schedule_dose_id') && ! $scheduleDose) {
                $validator->errors()->add('schedule_dose_id', 'Choose a configured schedule dose.');
            }

            if (! $scheduleDose) {
                return;
            }

            $schedule = $scheduleDose->schedule;
            $effectiveDate = $administrationTime->toDateString();

            if ($schedule->approval_status !== 'approved'
                || ! $schedule->effective_from
                || $schedule->effective_from->toDateString() > $effectiveDate
                || ($schedule->effective_until && $schedule->effective_until->toDateString() < $effectiveDate)) {
                $validator->errors()->add('schedule_dose_id', 'Choose a dose from a schedule version approved and effective on the administration date.');
            }

            if ($scheduleDose->vaccine_id !== $this->integer('vaccine_id') || $scheduleDose->dose_number !== $this->integer('dose_number')) {
                $validator->errors()->add('schedule_dose_id', 'The selected vaccine and dose number must match the configured schedule dose.');
            }

            if ($scheduleDose->minimum_age_days !== null
                && CarbonImmutable::parse($child->date_of_birth)->addDays($scheduleDose->minimum_age_days)->toDateString() > $effectiveDate) {
                $validator->errors()->add('administered_at', 'The administration date is earlier than the configured minimum age for this dose.');
            }

            if ($scheduleDose->minimum_interval_days === null || $scheduleDose->dose_number < 2) {
                return;
            }

            $previousDose = ScheduleDose::query()
                ->where('schedule_id', $scheduleDose->schedule_id)
                ->where('vaccine_id', $scheduleDose->vaccine_id)
                ->where('dose_number', '<', $scheduleDose->dose_number)
                ->orderByDesc('dose_number')
                ->first();

            if (! $previousDose) {
                return;
            }

            $previousRecord = VaccinationRecord::query()
                ->where('child_id', $child->id)
                ->where('schedule_dose_id', $previousDose->id)
                ->where('status', 'completed')
                ->orderByDesc('administered_at')
                ->first();

            if ($previousRecord && $previousRecord->administered_at->startOfDay()->diffInDays($administrationTime->startOfDay()) < $scheduleDose->minimum_interval_days) {
                $validator->errors()->add('administered_at', 'The administration date is earlier than the configured minimum interval from the previous dose.');
            }
        }];
    }
}
