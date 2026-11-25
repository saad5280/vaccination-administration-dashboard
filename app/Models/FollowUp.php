<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    protected $fillable = [
        'child_id', 'appointment_id', 'schedule_dose_id', 'assigned_to', 'outcome',
        'contacted_at', 'next_action_at', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime', 'next_action_at' => 'date'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scheduleDose(): BelongsTo
    {
        return $this->belongsTo(ScheduleDose::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
