<?php

namespace App\Models\Concerns;

use App\Models\Chamber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToChamber
{
    protected static function bootBelongsToChamber(): void
    {
        static::addGlobalScope('chamber', function (Builder $builder): void {
            $chamberId = auth()->user()?->chamber_id;
            $column = $builder->getModel()->qualifyColumn('chamber_id');

            if ($chamberId === null) {
                $builder->whereNull($column);

                return;
            }

            $builder->where($column, $chamberId);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('chamber_id') === null && auth()->hasUser()) {
                $model->setAttribute('chamber_id', auth()->user()->chamber_id);
            }
        });
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function scopeForChamber(Builder $query, int $chamberId): Builder
    {
        return $query
            ->withoutGlobalScope('chamber')
            ->where($query->getModel()->qualifyColumn('chamber_id'), $chamberId);
    }
}
