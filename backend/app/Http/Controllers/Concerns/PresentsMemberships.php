<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AthleteEntry;
use App\Models\AthleteProfile;
use App\Models\Membership;
use App\Models\MembershipEvent;
use App\Models\Payment;
use App\Models\User;
use App\Support\MembershipLedger;

/** Shared response shapes for memberships, payments and athlete records (R-31 to R-41). */
trait PresentsMemberships
{
    /**
     * One membership. Health answers, the medical file and emergency contacts are left
     * out entirely unless the viewer may see health details (R-32, R-40).
     */
    protected function membershipPayload(Membership $membership, bool $withHealth, bool $withHistory = false): array
    {
        $ledger = app(MembershipLedger::class);
        $plan = $membership->plan;

        $data = [
            'id' => $membership->id,
            'gym_id' => $membership->gym_id,
            'gym_name' => $membership->gym->name,
            'status' => $membership->status->value,
            'member' => $this->personPayload($membership->user),
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'billing_type' => $plan->billing_type?->value,
                'duration_months' => $plan->duration_months,
                'session_count' => $plan->session_count,
                'total_fee' => $plan->total_fee,
                'max_prepay_terms' => $plan->max_prepay_terms ?? 1,
                'prepay_discount_percent' => $plan->prepay_discount_percent ?? '0.00',
                'inclusions' => $plan->inclusions ?? [],
                'max_freeze_days' => $plan->max_freeze_days,
                'refundable' => $plan->refundable,
                'policy_freezing' => $plan->policy_freezing,
                'policy_renewal' => $plan->policy_renewal,
                'policy_cancellation' => $plan->policy_cancellation,
            ] : null,
            'background' => $membership->background,
            'current_rank' => $membership->current_rank,
            'training_history' => $membership->training_history,
            'signature_name' => $membership->signature_name,
            'signed_on' => $membership->signed_on?->toDateString(),
            'amount_charged' => number_format((float) $membership->amount_charged, 2, '.', ''),
            // R-110: the fee this membership keeps on renewal while it stays active or frozen.
            'locked_fee' => $membership->locked_fee,
            'prepaid_terms' => $membership->prepaid_terms ?? 1,
            // R-111: the promo or voucher taken off the first charge.
            'voucher_code' => $membership->voucher_code,
            'discount_amount' => number_format((float) $membership->discount_amount, 2, '.', ''),
            'offer_name' => $membership->offer_id ? $membership->offer?->name : null,
            'amount_paid' => number_format($ledger->paid($membership), 2, '.', ''),
            'balance' => number_format($ledger->balance($membership), 2, '.', ''),
            'starts_on' => $membership->starts_on?->toDateString(),
            'ends_on' => $membership->ends_on?->toDateString(),
            'sessions_remaining' => $membership->sessions_remaining,
            'freeze_days_used' => $membership->freeze_days_used,
            'frozen_until' => $membership->frozen_until?->toDateString(),
            'decided_by' => $membership->decidedBy ? $this->personName($membership->decidedBy) : null,
            'decided_at' => $membership->decided_at?->toIso8601String(),
            'decision_reason' => $membership->decision_reason,
            'created_at' => $membership->created_at?->toIso8601String(),
        ];

        if ($withHealth) {
            $data['health'] = $this->healthPayload($membership);
        }

        if ($withHistory) {
            $data['payments'] = $membership->payments()->with('receivedBy')->orderByDesc('paid_at')->orderByDesc('id')->get()
                ->map(fn (Payment $payment) => $this->paymentPayload($payment))->all();
            $data['history'] = $membership->events()->with('byUser')->orderByDesc('created_at')->orderByDesc('id')->get()
                ->map(fn (MembershipEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'note' => $event->note,
                    'by' => $event->byUser ? $this->personName($event->byUser) : null,
                    'at' => $event->created_at?->toIso8601String(),
                ])->all();
        }

        return $data;
    }

    protected function healthPayload(Membership|AthleteProfile $source): array
    {
        return [
            'emergency_contacts' => $source->emergency_contacts ?? [],
            'answers' => $source->health_answers ?? [],
            'notes' => $source->health_notes,
            'medical_file_name' => $source->medical_file_name,
        ];
    }

    protected function paymentPayload(Payment $payment): array
    {
        $session = $payment->session_booking_id ? $payment->booking?->session()->withoutGlobalScope('gym')->first() : null;

        return [
            'id' => $payment->id,
            'membership_id' => $payment->membership_id,
            'session_booking_id' => $payment->session_booking_id,
            'session' => $session ? [
                'name' => $session->name,
                'starts_at' => $session->starts_at->format('Y-m-d H:i:s'),
            ] : null,
            'member' => $payment->relationLoaded('user')
                ? ($payment->user ? $this->personPayload($payment->user) : ['id' => null, 'name' => $payment->payer_name ?? 'Walk-in', 'email' => null])
                : null,
            'amount' => $payment->amount,
            'method' => $payment->method->value,
            'receipt_number' => $payment->receipt_number,
            'received_by' => $payment->receivedBy ? $this->personName($payment->receivedBy) : null,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'note' => $payment->note,
            'corrects_id' => $payment->corrects_id,
        ];
    }

    protected function entryPayload(AthleteEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'discipline' => $entry->discipline,
            'title' => $entry->title,
            'division' => $entry->division,
            'result' => $entry->result,
            'occurred_on' => $entry->occurred_on?->toDateString(),
            'notes' => $entry->notes,
            'recorded_by' => $entry->recordedBy ? $this->personName($entry->recordedBy) : null,
        ];
    }

    protected function personPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $this->personName($user),
            'email' => $user->email,
        ];
    }

    protected function personName(User $user): string
    {
        return trim("{$user->first_name} {$user->last_name}");
    }
}
