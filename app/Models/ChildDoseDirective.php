<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildDoseDirective extends Model
{
    protected $fillable = ['child_id', 'schedule_dose_id', 'status', 'due_date_override', 'reason', 'authorized_by'];

    protected function casts(): array
    {
        return ['due_date_override' => 'date'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function scheduleDose(): BelongsTo
    {
        return $this->belongsTo(ScheduleDose::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
