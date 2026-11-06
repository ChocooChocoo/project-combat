<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One recorded payment (R-34, R-35). Never edited after it is saved: a correction or
 * refund is its own entry with a negative amount pointing at the original.
 */
#[Fillable([
    'membership_id', 'user_id', 'amount', 'method', 'received_by', 'paid_at', 'note', 'corrects_id',
    'paymongo_checkout_id', 'paymongo_payment_id', 'session_booking_id',
    'income_type', 'discount', 'payer_name',
])]
class Payment extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        // R-63: the income type is set once, from what the payment is for.
        static::creating(function (Payment $payment) {
            if (filled($payment->income_type)) {
                if (! array_key_exists($payment->income_type, config('finance.income_types', []))) {
                    throw new \LogicException("Unknown income type: {$payment->income_type}.");
                }

                return;
            }

            if ($payment->corrects_id) {
                $payment->income_type = static::withoutGlobalScope('gym')
                    ->whereKey($payment->corrects_id)->value('income_type');

                if (filled($payment->income_type)) {
                    return;
                }
            }

            if ($payment->membership_id) {
                $payment->income_type = 'membership';

                return;
            }

            if ($payment->session_booking_id) {
                $type = DB::table('session_bookings')
                    ->join('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
                    ->where('session_bookings.id', $payment->session_booking_id)
                    ->value('training_sessions.type');

                $payment->income_type = $type === 'private_coaching' ? 'private_coaching' : 'session';

                return;
            }

            throw new \LogicException('A payment without a membership or booking needs an income type.');
        });

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
            'discount' => 'decimal:2',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SessionBooking::class, 'session_booking_id')->withoutGlobalScope('gym');
    }
}
