<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReminderTaskGenerator
{
    public function __construct(private readonly DoseStatusCalculator $calculator) {}

    public function generate(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;
        $rules = ReminderRule::query()->with('template')->where('is_active', true)->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $dueRules = $rules->whereIn('trigger_type', ['before_due', 'on_due']);
        if ($dueRules->isNotEmpty()) {
            $activeScheduleIds = Schedule::query()
                ->where('approval_status', 'approved')
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
                ->pluck('id');
            $scheduleDoses = ScheduleDose::query()
                ->with(['vaccine:id,code,name', 'schedule:id,name,version'])
                ->where('is_required', true)
                ->whereIn('schedule_id', $activeScheduleIds)
                ->orderBy('vaccine_id')->orderBy('dose_number')->get();
            $children = Child::query()->where('status', 'active')
                ->with(['guardians' => fn ($query) => $query->wherePivot('is_primary', true)->wherePivot('consent_status', 'granted')])
                ->with(['vaccinationRecords' => fn ($query) => $query->where('status', 'completed')->orderByDesc('administered_at')])
                ->orderBy('id')->get();

            foreach ($children as $child) {
                foreach ($this->calculator->forChild($child, $scheduleDoses, $today) as $row) {
                    if (! $row['due_date'] || ! $row['schedule_dose']) {
                        continue;
                    }

                    foreach ($dueRules as $rule) {
                        $targetDate = match ($rule->trigger_type) {
                            'before_due' => $row['due_date']->subDays($rule->offset_days),
                            'on_due' => $row['due_date'],
                        };

                        if ($targetDate->toDateString() !== $today->toDateString()) {
                            continue;
                        }

                        $key = "rule:{$rule->id}:child:{$child->id}:dose:{$row['schedule_dose']->id}:due:{$row['due_date']->toDateString()}";
                        $created += $this->insertReminder(
                            $rule,
                            $child->id,
                            $child->guardians->first()?->id,
                            $row['schedule_dose']->id,
                            null,
                            $today,
                            $key,
                        );
                    }
                }
            }
        }

        $missedRules = $rules->where('trigger_type', 'after_missed_appointment');
        foreach ($missedRules as $rule) {
            $targetDate = $today->subDays($rule->offset_days)->toDateString();
            $appointments = Appointment::query()
                ->with(['child.guardians' => fn ($query) => $query->wherePivot('is_primary', true)->wherePivot('consent_status', 'granted')])
                ->where('status', 'missed')
                ->whereDate('scheduled_at', $targetDate)
                ->get();

            foreach ($appointments as $appointment) {
                $key = "rule:{$rule->id}:appointment:{$appointment->id}";
                $created += $this->insertReminder(
                    $rule,
                    $appointment->child_id,
                    $appointment->child->guardians->first()?->id,
                    $appointment->schedule_dose_id,
                    $appointment->id,
                    $today,
                    $key,
                );
            }
        }

        return $created;
    }

    private function insertReminder(
        ReminderRule $rule,
        int $childId,
        ?int $guardianId,
        ?int $scheduleDoseId,
        ?int $appointmentId,
        CarbonImmutable $today,
        string $idempotencyKey,
    ): int {
        $now = now();
        $inserted = DB::table('reminders')->insertOrIgnore([
            'child_id' => $childId,
            'guardian_id' => $guardianId,
            'schedule_dose_id' => $scheduleDoseId,
            'appointment_id' => $appointmentId,
            'template_id' => $rule->template_id,
            'template_name_snapshot' => $rule->template?->name,
            'template_body_snapshot' => $rule->template?->body,
            'reminder_rule_id' => $rule->id,
            'idempotency_key' => $idempotencyKey,
            'channel' => 'in_app',
            'status' => 'pending',
            'scheduled_for' => $today->setTime(9, 0)->format('Y-m-d H:i:s'),
            'sent_at' => null,
            'failure_reason' => null,
            'outcome_notes' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted) {
            $reminderId = DB::table('reminders')->where('idempotency_key', $idempotencyKey)->value('id');
            AuditLog::query()->create([
                'actor_id' => null,
                'action' => 'reminder.generated',
                'auditable_type' => Reminder::class,
                'auditable_id' => $reminderId,
                'before_values' => null,
                'after_values' => ['status' => 'pending', 'channel' => 'in_app', 'rule_id' => $rule->id],
                'ip_address' => null,
                'occurred_at' => $now,
            ]);
        }

        return $inserted;
    }
}
