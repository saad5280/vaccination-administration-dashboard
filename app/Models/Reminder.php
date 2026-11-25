<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'child_id', 'guardian_id', 'schedule_dose_id', 'appointment_id', 'template_id',
        'reminder_rule_id', 'idempotency_key', 'channel', 'status', 'scheduled_for',
        'sent_at', 'failure_reason', 'outcome_notes', 'created_by', 'template_name_snapshot', 'template_body_snapshot',
    ];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function scheduleDose(): BelongsTo
    {
        return $this->belongsTo(ScheduleDose::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class, 'template_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReminderRule::class, 'reminder_rule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
