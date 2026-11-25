<?php

namespace App\Models;

use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name', 'phone', 'alternate_phone', 'email', 'address',
        'preferred_contact_method', 'preferred_language',
    ];

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class)
            ->withPivot(['relationship', 'is_primary', 'consent_status', 'consent_recorded_at', 'consent_recorded_by'])
            ->withTimestamps();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
