<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One confirmed PayMongo payment for a gym's plan (R-105). Never edited. */
#[Fillable([
    'gym_id', 'subscription_plan_id', 'plan_name', 'amount', 'paymongo_checkout_id', 'paymongo_payment_id',
    'period_starts_at', 'period_ends_at', 'paid_by',
])]
class SubscriptionPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }
}
