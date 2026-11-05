<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded payment (R-34, R-35). Never edited after it is saved: a correction or
 * refund is its own entry with a negative amount pointing at the original.
 */
#[Fillable([
    'membership_id', 'user_id', 'amount', 'method', 'received_by', 'paid_at', 'note', 'corrects_id',
    'paymongo_checkout_id', 'paymongo_payment_id',
])]
class Payment extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        // R-34: a recorded payment cannot be silently changed. Only the receipt number is filled in once.
        static::updating(function (Payment $payment) {
            if (array_diff(array_keys($payment->getDirty()), ['receipt_number', 'updated_at'])) {
                throw new \LogicException('Payments cannot be changed. Record a correction instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Payments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withoutGlobalScope('gym');
    }
}
