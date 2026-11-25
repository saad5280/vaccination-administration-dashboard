<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class, 'user_centres');
    }

    public function isCentreScoped(): bool
    {
        return ! $this->hasRole('administrator')
            && $this->roles()->whereIn('roles.name', ['vaccinator', 'registration'])->exists();
    }

    public function canAccessChild(Child $child): bool
    {
        return ! $this->isCentreScoped() || ($child->centre_id !== null && $this->centres()->whereKey($child->centre_id)->exists());
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    public function approvedSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'approved_by_id');
    }

    public function administeredVaccinations(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class, 'administered_by');
    }

    public function createdVaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class, 'created_by');
    }

    public function assignedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'vaccinator_id');
    }

    public function createdAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'created_by');
    }

    public function assignedFollowUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'assigned_to');
    }

    public function recordedFollowUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'recorded_by');
    }

    public function createdReminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'created_by');
    }

    public function recordedStockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'recorded_by');
    }

    public function recordedStockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'recorded_by');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permissions.name', $permission))
            ->exists();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('roles.name', $role)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
