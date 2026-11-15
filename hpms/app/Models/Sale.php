<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChamber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToChamber;

    protected $fillable = ['chamber_id', 'sale_number', 'sale_date', 'total_amount', 'status', 'notes', 'cancellation_reason', 'cancelled_at', 'user_id'];

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'total_amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
