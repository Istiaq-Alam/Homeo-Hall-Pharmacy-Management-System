<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChamber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToChamber;

    protected $fillable = ['chamber_id', 'medicine_id', 'movement_type', 'quantity', 'reference_type', 'reference_id', 'previous_stock', 'resulting_stock', 'reason', 'movement_date'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'previous_stock' => 'integer', 'resulting_stock' => 'integer', 'movement_date' => 'datetime'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
