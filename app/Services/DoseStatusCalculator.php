<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ScheduleDose;
use App\Models\VaccinationRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DoseStatusCalculator
{
    /**
     * @param  Collection<int, ScheduleDose>  $scheduleDoses
     * @return array<int, array<string, mixed>>
     */
    public function forChild(Child $child, Collection $scheduleDoses, CarbonImmutable $today): array
    {
        $assignment = $child->scheduleAssignments->first();
        $scheduleIds = $scheduleDoses->pluck('schedule_id')->unique();

        if ($assignment) {
            $scheduleDoses = $scheduleDoses->where('schedule_id', $assignment->schedule_id);
        } elseif ($scheduleIds->count() === 1) {
            $scheduleDoses = $scheduleDoses->where('schedule_id', $scheduleIds->first());
        } elseif ($scheduleIds->count() > 1) {
            return [[
                'child' => $child, 'vaccine' => null, 'schedule' => null, 'schedule_dose' => null,
                'dose_number' => null, 'due_date' => null, 'days_overdue' => null,
                'last_dose' => $child->vaccinationRecords->where('status', 'completed')->sortByDesc('administered_at')->first(),
                'status' => 'unknown_history', 'directive' => null,
                'next_action' => 'Assign the approved schedule version applicable to this child',
            ]];
        }

        /** @var Collection<int, VaccinationRecord> $records */
        $records = $child->vaccinationRecords;
        $completedRecords = $records->where('status', 'completed');

        if ($completedRecords->isEmpty()) {
            return [[
                'child' => $child,
                'vaccine' => null,
                'schedule' => null,
                'schedule_dose' => null,
                'dose_number' => null,
                'due_date' => null,
                'days_overdue' => null,
                'last_dose' => null,
                'directive' => null,
                'status' => 'unknown_history',
                'next_action' => 'Review vaccination history with the guardian',
            ]];
        }

        if ($scheduleDoses->isEmpty()) {
            $lastDose = $completedRecords->sortByDesc('administered_at')->first();

            return [[
                'child' => $child,
                'vaccine' => null,
                'schedule' => $assignment?->schedule,
                'schedule_dose' => null,
                'dose_number' => null,
                'due_date' => null,
                'days_overdue' => null,
                'last_dose' => $lastDose,
                'status' => 'unknown_history',
                'directive' => null,
                'next_action' => $assignment
                    ? 'Review the assigned schedule and ensure an approved version is effective'
                    : 'Assign an effective schedule approved by the responsible health authority',
            ]];
        }

        $rows = [];
        $directives = $child->doseDirectives->keyBy('schedule_dose_id');

        foreach ($scheduleDoses as $dose) {
            $recordedDose = $completedRecords
                ->where('vaccine_id', $dose->vaccine_id)
                ->where('dose_number', $dose->dose_number)
                ->sortByDesc('administered_at')
                ->first();

            if ($recordedDose) {
                continue;
            }

            $dueDate = $dose->recommended_age_days === null
                ? null
                : CarbonImmutable::parse($child->date_of_birth)->addDays($dose->recommended_age_days);
            $directive = $directives->get($dose->id);

            $previousRecord = $completedRecords
                ->where('vaccine_id', $dose->vaccine_id)
                ->where('dose_number', '<', $dose->dose_number)
                ->sortByDesc('dose_number')
                ->first();

            // A minimum interval is an eligibility floor, not the recommended due interval.
            $intervalDays = $dose->interval_from_previous_days;

            if ($previousRecord && $intervalDays !== null) {
                $intervalDate = CarbonImmutable::parse($previousRecord->administered_at)->startOfDay()->addDays($intervalDays);
                $dueDate = $dueDate?->max($intervalDate) ?? $intervalDate;
            }

            if ($directive?->due_date_override) {
                $dueDate = CarbonImmutable::parse($directive->due_date_override);
            }

            if ($directive && $directive->status !== 'active') {
                $status = $directive->status;
            } elseif ($dueDate === null) {
                $status = 'unknown_history';
            } elseif ($dueDate->gt($today->addDays(30))) {
                $status = 'not_yet_due';
            } elseif ($dueDate->gt($today)) {
                $status = 'upcoming';
            } elseif ($dueDate->isSameDay($today)) {
                $status = 'due';
            } else {
                $status = 'overdue';
            }

            $lastDose = $completedRecords
                ->where('vaccine_id', $dose->vaccine_id)
                ->sortByDesc('dose_number')
                ->first();

            $rows[] = [
                'child' => $child,
                'vaccine' => $dose->vaccine,
                'schedule' => $dose->schedule,
                'schedule_dose' => $dose,
                'dose_number' => $dose->dose_number,
                'due_date' => $dueDate,
                'days_overdue' => $status === 'overdue' && $dueDate ? $dueDate->diffInDays($today) : null,
                'last_dose' => $lastDose,
                'status' => $status,
                'directive' => $directive,
                'next_action' => $status === 'unknown_history'
                    ? 'Review schedule dates and prior dose history'
                    : ($dose->dose_number > 1 && ! $previousRecord
                        ? 'Review prior dose history before follow-up'
                        : 'Contact guardian or book an appointment'),
            ];
        }

        return $rows;
    }
}
