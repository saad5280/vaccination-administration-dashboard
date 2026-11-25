<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderRule extends Model
{
    protected $fillable = ['name', 'trigger_type', 'offset_days', 'template_id', 'channel', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['offset_days' => 'integer', 'is_active' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
