<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A platform plan a gym pays for (R-102). `modules` lists the owner-side modules it may use (R-103). */
#[Fillable(['name', 'monthly_price', 'inclusions', 'modules'])]
class SubscriptionPlan extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'modules' => 'array',
            'is_free' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('retired_at');
    }
}
