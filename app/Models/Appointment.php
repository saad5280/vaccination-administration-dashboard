<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'child_id', 'schedule_dose_id', 'centre_id', 'vaccinator_id', 'scheduled_at',
        'status', 'cancellation_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function scheduleDose(): BelongsTo
    {
        return $this->belongsTo(ScheduleDose::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function vaccinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vaccinator_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
