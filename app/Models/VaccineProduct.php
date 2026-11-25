<?php

namespace App\Models;

use Database\Factories\VaccineProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineProduct extends Model
{
    /** @use HasFactory<VaccineProductFactory> */
    use HasFactory;

    protected $fillable = [
        'vaccine_id', 'manufacturer', 'product_name', 'presentation', 'route',
        'storage_requirements', 'doses_per_vial', 'stock_unit', 'dose_quantity',
        'dose_unit', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'dose_quantity' => 'decimal:3'];
    }

    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    public function reorderLevels(): HasMany
    {
        return $this->hasMany(StockReorderLevel::class);
    }
}
