<?php

namespace App\Models;

use Database\Factories\StockTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransaction extends Model
{
    /** @use HasFactory<StockTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_batch_id', 'centre_id', 'type', 'quantity', 'reference_type', 'reference_id',
        'related_transaction_id', 'reason', 'recorded_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'occurred_at' => 'datetime'];
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id');
    }
}
