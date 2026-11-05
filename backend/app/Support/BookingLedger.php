<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\SessionBooking;
use App\Models\User;
use App\Notifications\BookingUpdated;

/** R-51: fees stay owed until real money is recorded. */
class BookingLedger
{
    public function paid(SessionBooking $booking): float
    {
        return round((float) $booking->payments()->sum('amount'), 2);
    }

    public function balance(SessionBooking $booking): float
    {
        if (! in_array($booking->status, [BookingStatus::Requested, BookingStatus::Booked], true)) {
            return 0;
        }

        return round((float) $booking->fee - $this->paid($booking), 2);
    }

    public function state(SessionBooking $booking): string
    {
        $paid = $this->paid($booking);
        // Q-20 refunds are money handed back; correcting a mistaken entry leaves the fee owed.
        if ($paid === 0.0 && $booking->payments()->where('amount', '<', 0)->where('note', 'like', 'Session cancelled. Full refund%')->exists()) {
            return 'refunded';
        }
        if (! in_array($booking->status, [BookingStatus::Requested, BookingStatus::Booked], true)) {
            return $paid > 0 ? 'paid' : 'none';
        }
        if ((float) $booking->fee === 0.0) {
            return 'included';
        }

        return $paid > 0 && $this->balance($booking) <= 0 ? 'paid' : 'owed';
    }

    public function recordPayment(SessionBooking $booking, float $amount, PaymentMethod $method, ?User $by, array $extra = []): Payment
    {
        app(CurrentGym::class)->set($booking->gym);
        $payment = Payment::create([
            'session_booking_id' => $booking->id, 'user_id' => $booking->user_id,
            'amount' => $amount, 'method' => $method, 'received_by' => $by?->id, 'paid_at' => now(), ...$extra,
        ]);
        $payment->receipt_number = sprintf('OR-%d-%06d', $payment->gym_id, $payment->id);
        $payment->save();
        $booking->user->notify(new BookingUpdated($booking, $amount < 0 ? 'refund' : 'payment'));

        return $payment;
    }

    /** R-45, Q-20: caller locks the booking. Staff hand money back, never an API refund. */
    public function refund(SessionBooking $booking, ?User $by): void
    {
        if ($this->paid($booking) <= 0) {
            return;
        }
        foreach ($booking->payments()->where('amount', '>', 0)->get() as $original) {
            if ($booking->payments()->where('corrects_id', $original->id)->exists()) {
                continue;
            }
            $this->recordPayment($booking, -(float) $original->amount, $original->method, $by, [
                'corrects_id' => $original->id,
                'note' => "Session cancelled. Full refund of {$original->receipt_number}; staff hand the money back to the athlete.",
            ]);
        }
    }
}
