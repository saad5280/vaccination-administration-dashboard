<?php

namespace App\Models;

use Database\Factories\CentreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Centre extends Model
{
    /** @use HasFactory<CentreFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'phone', 'email', 'address', 'locality', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_centres');
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockReorderLevels(): HasMany
    {
        return $this->hasMany(StockReorderLevel::class);
    }
}
