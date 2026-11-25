<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    protected $fillable = ['source_batch_id', 'destination_batch_id', 'quantity', 'reason', 'recorded_by', 'transferred_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'transferred_at' => 'datetime'];
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'source_batch_id');
    }

    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'destination_batch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
