<?php

namespace App\Models;

use Database\Factories\VaccinationRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VaccinationRecord extends Model
{
    /** @use HasFactory<VaccinationRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'child_id', 'vaccine_id', 'schedule_dose_id', 'dose_number', 'administered_at',
        'stock_batch_id', 'lot_number', 'batch_expiry_date', 'manufacturer', 'product_name',
        'dose_quantity', 'dose_unit', 'route', 'administration_site', 'centre_id',
        'administered_by', 'status', 'notes', 'supersedes_record_id', 'correction_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'datetime',
            'batch_expiry_date' => 'date',
            'dose_quantity' => 'decimal:3',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    public function scheduleDose(): BelongsTo
    {
        return $this->belongsTo(ScheduleDose::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_record_id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_record_id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
