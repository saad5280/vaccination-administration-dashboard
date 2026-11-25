<?php

namespace App\Models;

use Database\Factories\VaccineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vaccine extends Model
{
    /** @use HasFactory<VaccineFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'target_disease', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(VaccineProduct::class);
    }

    public function scheduleDoses(): HasMany
    {
        return $this->hasMany(ScheduleDose::class);
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }
}
