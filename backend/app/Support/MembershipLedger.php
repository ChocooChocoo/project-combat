<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlanBilling;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\MembershipUpdated;
use Illuminate\Support\Carbon;

/** Money and history for one membership (R-34 to R-36). Every change goes through here. */
class MembershipLedger
{
    public function paid(Membership $membership): float
    {
        return (float) $membership->payments()->sum('amount');
    }

    /** What is still owed. Negative means the member paid more than was charged. */
    public function balance(Membership $membership): float
    {
        return round((float) $membership->amount_charged - $this->paid($membership), 2);
    }

    /**
     * Records one payment with its receipt number. A payment never approves an
     * application (R-33); it only activates a membership already approved and waiting for payment.
     */
    public function recordPayment(Membership $membership, float $amount, PaymentMethod $method, ?User $receivedBy, array $extra = []): Payment
    {
        app(CurrentGym::class)->set($membership->gym);

        $payment = Payment::create([
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'amount' => $amount,
            'method' => $method,
            'received_by' => $receivedBy?->id,
            'paid_at' => now(),
            ...$extra,
        ]);
        $payment->receipt_number = sprintf('OR-%d-%06d', $payment->gym_id, $payment->id);
        $payment->save();

        $type = $amount < 0 ? (isset($extra['corrects_id']) ? 'correction' : 'refund') : 'payment';
        $this->log($membership, $type, $receivedBy, sprintf('%s of PHP %s, receipt %s', ucfirst($type), number_format(abs($amount), 2), $payment->receipt_number));

        $this->activateIfPaid($membership, $receivedBy);

        return $payment;
    }

    public function activateIfPaid(Membership $membership, ?User $by = null): void
    {
        if ($membership->status !== MembershipStatus::AwaitingPayment || $this->balance($membership) > 0) {
            return;
        }

        $membership->status = MembershipStatus::Active;
        $this->startPeriod($membership, today());
        $membership->save();

        $this->log($membership, 'activated', $by, $this->periodNote($membership));
    }

    /** A fresh period from the given day: an end date for time plans, a session count for passes. */
    public function startPeriod(Membership $membership, Carbon $from): void
    {
        $plan = $membership->plan;
        $membership->starts_on = $from->toDateString();

        if ($plan->billing_type === PlanBilling::Sessions) {
            $membership->sessions_remaining = $plan->session_count;
            $membership->ends_on = null;
        } else {
            $membership->sessions_remaining = null;
            $membership->ends_on = $from->copy()->addMonthsNoOverflow(MembershipPricing::months($plan, (int) ($membership->prepaid_terms ?? 1)))->subDay()->toDateString();
        }

        $membership->freeze_days_used = 0;
    }

    public function periodNote(Membership $membership): ?string
    {
        if ($membership->ends_on) {
            return 'Valid until '.$membership->ends_on->toDateString();
        }

        return $membership->sessions_remaining !== null
            ? $membership->sessions_remaining.($membership->sessions_remaining === 1 ? ' session left' : ' sessions left')
            : null;
    }

    /** Keeps the change in the history and tells the athlete (R-36). */
    public function log(Membership $membership, string $type, ?User $by, ?string $note = null): void
    {
        app(CurrentGym::class)->set($membership->gym);

        $membership->events()->create([
            'type' => $type,
            'by_user_id' => $by?->id,
            'note' => $note,
        ]);

        if ($type !== 'applied') {
            $membership->user->notify(new MembershipUpdated($membership, $type, $note));
        }
    }
}
