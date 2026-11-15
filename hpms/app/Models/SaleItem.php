<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'medicine_id', 'quantity', 'unit_selling_price', 'unit_cost_price', 'line_total', 'line_cost_total'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_selling_price' => 'decimal:2', 'unit_cost_price' => 'decimal:4', 'line_total' => 'decimal:2', 'line_cost_total' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
