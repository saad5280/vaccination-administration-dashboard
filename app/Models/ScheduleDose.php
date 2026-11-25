<?php

namespace App\Models;

use Database\Factories\ScheduleDoseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleDose extends Model
{
    /** @use HasFactory<ScheduleDoseFactory> */
    use HasFactory;

    protected $fillable = [
        'schedule_id', 'vaccine_id', 'dose_number', 'recommended_age_days',
        'minimum_age_days', 'interval_from_previous_days', 'minimum_interval_days',
        'is_required', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
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
