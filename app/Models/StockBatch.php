<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    /** @use HasFactory<StockBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'vaccine_product_id', 'centre_id', 'batch_number', 'supplier', 'received_at',
        'expires_at', 'quantity_received', 'available_quantity', 'storage_location', 'status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'expires_at' => 'date',
            'quantity_received' => 'decimal:3',
            'available_quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(VaccineProduct::class, 'vaccine_product_id');
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function sourceTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_batch_id');
    }

    public function destinationTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_batch_id');
    }

    public function routineEligibleOn(CarbonInterface $date): bool
    {
        return $this->status === 'active' && $this->expires_at->toDateString() >= $date->toDateString();
    }

    public function quantityForOneDose(): float
    {
        if (in_array(mb_strtolower($this->product->stock_unit), ['dose', 'doses'], true)) {
            return 1.0;
        }

        $dosesPerVial = $this->product->doses_per_vial;

        return $dosesPerVial ? round(1 / $dosesPerVial, 3) : 1.0;
    }
}
