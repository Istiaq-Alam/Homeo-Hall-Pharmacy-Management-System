<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'medicine_id', 'quantity', 'unit_cost', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
