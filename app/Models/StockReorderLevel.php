<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReorderLevel extends Model
{
    protected $fillable = ['vaccine_product_id', 'centre_id', 'minimum_quantity', 'updated_by'];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(VaccineProduct::class, 'vaccine_product_id');
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
