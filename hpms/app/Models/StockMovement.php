<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['medicine_id', 'movement_type', 'quantity', 'reference_type', 'reference_id', 'previous_stock', 'resulting_stock', 'reason', 'movement_date'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'previous_stock' => 'integer', 'resulting_stock' => 'integer', 'movement_date' => 'datetime'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
