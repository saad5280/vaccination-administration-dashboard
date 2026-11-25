<?php

namespace App\Models;

use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'version', 'effective_from', 'effective_until', 'approval_status',
        'approved_by_name', 'approved_by_id', 'approved_at', 'approval_reference', 'created_by',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date', 'approved_at' => 'datetime'];
    }

    public function doses(): HasMany
    {
        return $this->hasMany(ScheduleDose::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
