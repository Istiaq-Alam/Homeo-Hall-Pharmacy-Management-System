<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChamber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use BelongsToChamber;

    protected $fillable = ['chamber_id', 'medicine_id', 'adjustment_type', 'quantity', 'reason', 'adjustment_date', 'notes', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'adjustment_date' => 'date'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
