<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildScheduleAssignment extends Model
{
    protected $fillable = ['child_id', 'schedule_id', 'assigned_by', 'assigned_at', 'reason'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
