<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Models\Membership;
use App\Support\MembershipLedger;
use App\Support\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A person's own memberships in every gym (S-39), and paying online through
 * PayMongo (R-35). Each lookup is limited to the signed-in person, across gyms on purpose.
 */
class MyMembershipController extends Controller
{
    use PresentsMemberships;

    public function index(Request $request): JsonResponse
    {
        $memberships = Membership::withoutGlobalScope('gym')
            ->where('user_id', $request->user()->id)
            ->with(['gym', 'user', 'decidedBy'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Membership $membership) => $this->membershipPayload($membership, true))
            ->values();

        return response()->json(['memberships' => $memberships]);
    }

    public function show(Request $request, int $membership): JsonResponse
    {
        $model = $this->own($request, $membership);

        return response()->json(['membership' => $this->membershipPayload($model, true, true)]);
    }

    /** Starts, or reopens, the one checkout for what is owed. Paying twice is prevented here. */
    public function checkout(Request $request, int $membership, PayMongo $payMongo, MembershipLedger $ledger): JsonResponse
    {
        $model = $this->own($request, $membership);

        abort_unless(
            in_array($model->status, [MembershipStatus::AwaitingPayment, MembershipStatus::Active, MembershipStatus::Frozen], true),
            409,
            'This membership has nothing to pay online.',
        );

        try {
            // An open checkout is reused; a paid one is recorded first, so nobody pays the same fee twice.
            if ($model->pending_checkout_id) {
                $existing = $payMongo->checkout($model->pending_checkout_id);

                if ($existing['paid']) {
                    self::recordCheckout($model, $model->pending_checkout_id, $existing, $ledger);
                    $model->refresh();
                } elseif ($existing['status'] === 'active' && $existing['checkout_url']) {
                    return response()->json(['checkout_url' => $existing['checkout_url']]);
                }
            }

            $balance = $ledger->balance($model);

            if ($balance <= 0) {
                throw ValidationException::withMessages(['balance' => 'Nothing is owed on this membership.']);
            }

            $frontend = rtrim(config('app.frontend_url'), '/');
            $session = $payMongo->createCheckout(
                $balance,
                $model->plan->name,
                "Membership at {$model->gym->name}",
                "{$frontend}/memberships/{$model->id}?checkout=done",
                "{$frontend}/memberships/{$model->id}?checkout=cancelled",
                ['membership_id' => $model->id, 'gym_id' => $model->gym_id, 'user_id' => $model->user_id],
            );
        } catch (RuntimeException) {
            abort(502, 'Online payment is not available right now. Try again, or pay at the front desk.');
        }

        $model->forceFill(['pending_checkout_id' => $session['id']])->save();

        return response()->json(['checkout_url' => $session['checkout_url']]);
    }

    /** After PayMongo sends the person back: the backend asks PayMongo itself whether it was paid. */
    public function confirm(Request $request, int $membership, PayMongo $payMongo, MembershipLedger $ledger): JsonResponse
    {
        $model = $this->own($request, $membership);
        $paid = false;

        if ($model->pending_checkout_id) {
            try {
                $result = $payMongo->checkout($model->pending_checkout_id);
            } catch (RuntimeException) {
                abort(502, 'Could not reach PayMongo to confirm the payment. Try again in a moment.');
            }

            if ($result['paid']) {
                self::recordCheckout($model, $model->pending_checkout_id, $result, $ledger);
                $paid = true;
            }
        } else {
            // Already recorded, for example by the webhook.
            $paid = $model->payments()->whereNotNull('paymongo_checkout_id')->exists();
        }

        return response()->json([
            'paid' => $paid,
            'membership' => $this->membershipPayload($this->own($request, $model->id), true, true),
        ]);
    }

    /**
     * Records a confirmed checkout once. The unique checkout id on payments means the
     * webhook and the return page can never both record it.
     */
    public static function recordCheckout(Membership $membership, string $checkoutId, array $result, MembershipLedger $ledger): void
    {
        DB::transaction(function () use ($membership, $checkoutId, $result, $ledger) {
            $locked = Membership::withoutGlobalScope('gym')->lockForUpdate()->find($membership->id);

            if (! $locked->payments()->where('paymongo_checkout_id', $checkoutId)->exists()) {
                $ledger->recordPayment($locked, (float) $result['amount'], PaymentMethod::Paymongo, null, [
                    'paymongo_checkout_id' => $checkoutId,
                    'paymongo_payment_id' => $result['payment_id'],
                    'note' => 'Paid online through PayMongo',
                ]);
            }

            if ($locked->pending_checkout_id === $checkoutId) {
                $locked->forceFill(['pending_checkout_id' => null])->save();
            }
        });
    }

    private function own(Request $request, int $id): Membership
    {
        return Membership::withoutGlobalScope('gym')
            ->where('user_id', $request->user()->id)
            ->with(['gym', 'user', 'decidedBy'])
            ->findOrFail($id);
    }
}
