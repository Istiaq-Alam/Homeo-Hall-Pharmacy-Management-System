<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['purchase_number', 'purchase_date', 'supplier_name', 'total_amount', 'status', 'notes', 'cancellation_reason', 'cancelled_at', 'user_id'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'total_amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
