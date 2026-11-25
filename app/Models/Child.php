<?php

namespace App\Models;

use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    protected $fillable = [
        'registration_id', 'external_registration_number', 'first_name', 'last_name', 'date_of_birth', 'sex', 'photo_path',
        'address', 'locality', 'centre_id', 'registered_at', 'status', 'internal_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'registered_at' => 'date',
        ];
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->isCentreScoped()
            ? $query->whereIn('centre_id', $user->centres()->select('centres.id'))
            : $query;
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary', 'consent_status', 'consent_recorded_at', 'consent_recorded_by'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    public function doseDirectives(): HasMany
    {
        return $this->hasMany(ChildDoseDirective::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(ChildScheduleAssignment::class)->latest('assigned_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
