<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlanBilling;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Injury;
use App\Models\Membership;
use App\Support\MembershipLedger;
use App\Support\MembershipPricing;
use App\Support\OfferApplier;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** R-33 and R-36: deciding applications, and each membership's life after approval. */
class MembershipController extends Controller
{
    use PresentsMemberships;

    public function __construct(private MembershipLedger $ledger) {}

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $viewer = $request->user();
        abort_unless(
            $viewer->hasPermission($gym, 'members.memberships.view', 'training.sessions.view', 'training.athletes.view', 'finance.payments.view'),
            403,
            'You do not have access to this part of the gym.',
        );

        $data = $request->validate([
            'view' => ['nullable', Rule::in(['applications', 'members'])],
            'status' => ['nullable', Rule::enum(MembershipStatus::class)],
            ...Paging::rules(),
        ]);

        $query = $gym->memberships()->with(['gym', 'user', 'decidedBy']);

        if (($data['view'] ?? null) === 'applications') {
            $query->whereIn('status', [MembershipStatus::Applied->value, MembershipStatus::Rejected->value]);
        } elseif (($data['view'] ?? null) === 'members') {
            $query->whereNotIn('status', [MembershipStatus::Applied->value, MembershipStatus::Rejected->value]);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        [$rows, $meta] = Paging::rows($query->orderByDesc('created_at')->orderByDesc('id'), $request);
        // R-119: the restricted flag only, no injury details.
        $restricted = Injury::restrictedAmong($gym->id, $rows->pluck('user_id'));
        $memberships = $rows
            ->map(fn (Membership $membership) => [...$this->membershipPayload($membership, false), 'restricted' => isset($restricted[$membership->user_id])])
            ->values();

        return response()->json(['memberships' => $memberships, ...($meta ? ['meta' => $meta] : [])]);
    }

    public function show(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);

        return response()->json([
            'membership' => $this->membershipPayload($model, $this->canSeeHealth($request, $gym), true),
        ]);
    }

    /** R-111: what approving would charge, with the promo or voucher. Read-only; nothing is used up. */
    public function pricePreview(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);
        abort_unless($model->status === MembershipStatus::Applied, 409, 'Only waiting applications can be priced.');

        $code = $request->validate(['voucher_code' => ['nullable', 'string', 'max:40']])['voucher_code'] ?? null;
        $terms = (int) $model->prepaid_terms;
        $base = round((float) $model->plan->total_fee * $terms, 2);
        $charge = MembershipPricing::charge($model->plan, (float) $model->plan->total_fee, $terms);
        $offer = app(OfferApplier::class)->pick($model, $code, $charge);
        $discount = $offer ? $offer->discountOn($charge) : 0.0;

        return response()->json(['price' => [
            'base' => $base,
            'prepay_discount' => round($base - $charge, 2),
            'offer_name' => $offer?->name,
            'offer_discount' => $discount,
            'payable' => round($charge - $discount, 2),
        ]]);
    }

    /** R-33: only membership approvers and the owner reach this (route middleware). */
    public function decide(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);
        $user = $request->user();

        abort_if($user->id === $model->user_id, 403, 'You cannot decide your own application.');
        abort_unless($model->status === MembershipStatus::Applied, 409, 'Only waiting applications can be decided.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
        ], ['reason.required_if' => 'Give the applicant a reason.']);

        DB::transaction(function () use ($gym, $model, $user, $data) {
            $model->decided_by = $user->id;
            $model->decided_at = now();

            if ($data['decision'] === 'reject') {
                $model->status = MembershipStatus::Rejected;
                $model->decision_reason = $data['reason'];
                $model->save();
                $this->ledger->log($model, 'rejected', $user, $data['reason']);

                return;
            }

            // R-110: the fee is locked at approval; R-109: prepaid terms are charged together.
            $model->status = MembershipStatus::AwaitingPayment;
            $model->locked_fee = $model->plan->total_fee;
            $model->amount_charged = MembershipPricing::charge($model->plan, (float) $model->locked_fee, (int) $model->prepaid_terms);
            // R-111: a voucher given now replaces the one from the application.
            if (array_key_exists('voucher_code', $data)) {
                $model->voucher_code = filled($data['voucher_code']) ? strtoupper(trim($data['voucher_code'])) : null;
            }
            app(OfferApplier::class)->apply($model, $model->voucher_code);
            $model->decision_reason = $data['reason'] ?? null;
            $model->save();

            if (! $model->user->hasGymRole($gym, GymPersonType::Member)) {
                $gym->addPerson($model->user, GymPersonType::Member);
            }

            $this->fillAthleteProfile($gym, $model);
            $this->ledger->log($model, 'approved', $user, 'Pay PHP '.number_format((float) $model->amount_charged, 2).' to start');
            // A free plan starts at once.
            $this->ledger->activateIfPaid($model, $user);
        });

        return response()->json(['membership' => $this->membershipPayload($this->find($gym, $model->id), $this->canSeeHealth($request, $gym), true)]);
    }

    /**
     * R-36: renew for one or more periods. R-110: active and frozen memberships keep their locked fee;
     * an expired one restarts at the plan's current fee, which becomes the new locked fee.
     */
    public function renew(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);
        $plan = $model->plan;

        abort_unless(
            in_array($model->status, [MembershipStatus::Active, MembershipStatus::Frozen, MembershipStatus::Expired], true),
            409,
            'Only active, frozen or expired memberships can be renewed.',
        );

        $terms = (int) ($request->validate(['prepaid_terms' => MembershipPricing::termsRule($plan)], [
            'prepaid_terms.max' => 'This plan can be prepaid for up to '.max(1, (int) $plan->max_prepay_terms).' terms.',
        ])['prepaid_terms'] ?? 1);

        DB::transaction(function () use ($request, $model, $plan, $terms) {
            if ($model->status === MembershipStatus::Expired || $model->locked_fee === null) {
                $model->locked_fee = $plan->total_fee;
            }
            $charge = MembershipPricing::charge($plan, (float) $model->locked_fee, $terms);
            $model->amount_charged = (float) $model->amount_charged + $charge;
            $model->prepaid_terms = $terms;

            if ($model->status === MembershipStatus::Expired) {
                $model->status = MembershipStatus::AwaitingPayment;
                $note = 'Starts again once PHP '.number_format($charge, 2).' is paid';
            } elseif ($plan->billing_type === PlanBilling::Sessions) {
                $model->sessions_remaining = (int) $model->sessions_remaining + $plan->session_count;
                $note = $model->sessions_remaining.($model->sessions_remaining === 1 ? ' session left' : ' sessions left');
            } else {
                $model->ends_on = $model->ends_on->copy()->addMonthsNoOverflow(MembershipPricing::months($plan, $terms));
                $note = 'Valid until '.$model->ends_on->toDateString();
            }

            $model->save();
            $this->ledger->log($model, 'renewed', $request->user(), $note);
            $this->ledger->activateIfPaid($model, $request->user());
        });

        return $this->respond($request, $gym, $model);
    }

    /** R-36: freeze within the plan's limit. The end date moves by the frozen days. */
    public function freeze(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);
        $max = $model->plan->max_freeze_days;

        abort_unless($model->status === MembershipStatus::Active, 409, 'Only active memberships can be frozen.');
        abort_if($max === null, 422, 'This plan does not allow freezing.');

        $left = $max - $model->freeze_days_used;
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:'.max($left, 0)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], ['days.max' => $left > 0 ? "Only {$left} freeze days are left on this plan." : 'No freeze days are left on this plan.']);

        $days = (int) $data['days'];
        $model->status = MembershipStatus::Frozen;
        $model->frozen_until = today()->addDays($days - 1)->toDateString();
        $model->freeze_days_used += $days;
        if ($model->ends_on) {
            $model->ends_on = $model->ends_on->copy()->addDays($days);
        }
        $model->save();

        $note = "Frozen for {$days} days, until {$model->frozen_until->toDateString()}";
        if (! empty($data['reason'])) {
            $note .= ". {$data['reason']}";
        }
        $this->ledger->log($model, 'frozen', $request->user(), $note);

        return $this->respond($request, $gym, $model);
    }

    /** R-36: unfreezing early gives back the unused days. */
    public function unfreeze(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);

        abort_unless($model->status === MembershipStatus::Frozen, 409, 'Only frozen memberships can be unfrozen.');

        $unused = $model->frozen_until && $model->frozen_until->gte(today())
            ? (int) today()->diffInDays($model->frozen_until) + 1
            : 0;

        $model->status = MembershipStatus::Active;
        $model->frozen_until = null;
        $model->freeze_days_used = max(0, $model->freeze_days_used - $unused);
        if ($model->ends_on && $unused > 0) {
            $model->ends_on = $model->ends_on->copy()->subDays($unused);
        }
        $model->save();

        $this->ledger->log($model, 'unfrozen', $request->user(), $this->ledger->periodNote($model));

        return $this->respond($request, $gym, $model);
    }

    /** R-36: cancel under the plan's refund rule. A refund is its own payment entry. */
    public function cancel(Request $request, Gym $gym, int $membership): JsonResponse
    {
        $model = $this->find($gym, $membership);
        $refundable = $model->plan->refundable;

        abort_unless(
            in_array($model->status, [MembershipStatus::AwaitingPayment, MembershipStatus::Active, MembershipStatus::Frozen], true),
            409,
            'This membership cannot be cancelled.',
        );

        $paid = $this->ledger->paid($model);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'gt:0', 'max:'.max($paid, 0)],
            'refund_method' => ['required_with:refund_amount', 'nullable', Rule::in(array_keys(config('membership.desk_methods')))],
        ], [
            'reason.required' => 'Give a reason for cancelling.',
            'refund_amount.max' => 'A refund cannot be more than what was paid.',
        ]);

        if (! empty($data['refund_amount']) && ! $refundable) {
            throw ValidationException::withMessages(['refund_amount' => 'This plan does not give refunds on cancelling.']);
        }

        DB::transaction(function () use ($request, $model, $data) {
            $model->status = MembershipStatus::Cancelled;
            $model->frozen_until = null;
            $model->save();
            $this->ledger->log($model, 'cancelled', $request->user(), $data['reason']);

            if (! empty($data['refund_amount'])) {
                $this->ledger->recordPayment($model, -1 * (float) $data['refund_amount'], PaymentMethod::from($data['refund_method']), $request->user(), [
                    'note' => 'Refund on cancelling',
                ]);
            }
        });

        return $this->respond($request, $gym, $model);
    }

    /** R-32: the medical clearance sent with an application. Health permission only. */
    public function medicalFile(Request $request, Gym $gym, int $membership): StreamedResponse
    {
        abort_unless($this->canSeeHealth($request, $gym), 403);
        $model = $this->find($gym, $membership);
        abort_unless($model->medical_file_path, 404);

        return Storage::disk('local')->response($model->medical_file_path, $model->medical_file_name);
    }

    /** R-33: approval creates the athlete profile from the application, or refreshes it on rejoining. */
    private function fillAthleteProfile(Gym $gym, Membership $membership): void
    {
        $profile = $gym->athleteProfiles()->firstOrNew(['user_id' => $membership->user_id]);
        $profile->fill([
            'emergency_contacts' => $membership->emergency_contacts,
            'background' => $membership->background,
            'current_rank' => $membership->current_rank,
            'training_history' => $membership->training_history,
            'health_answers' => $membership->health_answers,
            'health_notes' => $membership->health_notes,
        ]);

        if ($membership->medical_file_path) {
            $profile->forceFill([
                'medical_file_path' => $membership->medical_file_path,
                'medical_file_name' => $membership->medical_file_name,
                'medical_file_mime' => $membership->medical_file_mime,
                'medical_file_size' => $membership->medical_file_size,
            ]);
        }

        $profile->save();
    }

    private function canSeeHealth(Request $request, Gym $gym): bool
    {
        return $request->user()->hasPermission($gym, 'health_records.records.view');
    }

    private function respond(Request $request, Gym $gym, Membership $model): JsonResponse
    {
        return response()->json(['membership' => $this->membershipPayload($this->find($gym, $model->id), $this->canSeeHealth($request, $gym), true)]);
    }

    private function find(Gym $gym, int $id): Membership
    {
        return $gym->memberships()->with(['gym', 'user', 'decidedBy'])->findOrFail($id);
    }
}
