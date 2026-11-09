<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Support\PayMongo;
use App\Support\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** R-105, R-107: the owner sees their plan and pays for one through PayMongo. Owner only. */
class SubscriptionController extends Controller
{
    public function show(Request $request, Gym $gym): JsonResponse
    {
        $this->ownerOnly($request, $gym);

        return response()->json($this->payload($gym->fresh()));
    }

    public function checkout(Request $request, Gym $gym, PayMongo $payMongo): JsonResponse
    {
        $this->ownerOnly($request, $gym);
        $data = $request->validate(['plan_id' => ['required', 'integer']]);
        $plan = SubscriptionPlan::active()->where('is_free', false)->find($data['plan_id']);
        abort_unless($plan, 422, 'Choose an available paid plan.');

        try {
            // A paid but unrecorded checkout is recorded first, so nobody pays the same month twice.
            if ($gym->pending_subscription_checkout_id) {
                $existing = $payMongo->checkout($gym->pending_subscription_checkout_id);
                if ($existing['paid']) {
                    Subscription::recordCheckout($gym, $gym->pending_subscription_checkout_id, $existing, $request->user()->id);
                    $gym->refresh();
                } elseif ($existing['status'] === 'active' && $existing['checkout_url'] && $gym->pending_subscription_plan_id === $plan->id) {
                    return response()->json(['checkout_url' => $existing['checkout_url']]);
                }
            }

            $frontend = rtrim(config('app.frontend_url'), '/');
            $session = $payMongo->createCheckout(
                (float) $plan->monthly_price,
                "{$plan->name} plan",
                "One month of the {$plan->name} plan for {$gym->name}",
                "{$frontend}/gyms/{$gym->id}/manage/subscription?checkout=done",
                "{$frontend}/gyms/{$gym->id}/manage/subscription?checkout=cancelled",
                ['gym_id' => $gym->id, 'subscription_plan_id' => $plan->id, 'user_id' => $request->user()->id],
            );
        } catch (RuntimeException) {
            abort(502, 'Online payment is not available right now. Try again in a moment.');
        }

        $gym->forceFill([
            'pending_subscription_checkout_id' => $session['id'],
            'pending_subscription_plan_id' => $plan->id,
        ])->save();

        return response()->json(['checkout_url' => $session['checkout_url']]);
    }

    /** After PayMongo sends the owner back, the backend asks PayMongo itself whether it was paid. */
    public function confirm(Request $request, Gym $gym, PayMongo $payMongo): JsonResponse
    {
        $this->ownerOnly($request, $gym);
        $paid = false;

        if ($gym->pending_subscription_checkout_id) {
            try {
                $result = $payMongo->checkout($gym->pending_subscription_checkout_id);
            } catch (RuntimeException) {
                abort(502, 'Could not reach PayMongo to confirm the payment. Try again in a moment.');
            }
            if ($result['paid']) {
                Subscription::recordCheckout($gym, $gym->pending_subscription_checkout_id, $result, $request->user()->id);
                $paid = true;
            }
        }

        return response()->json(['paid' => $paid, ...$this->payload($gym->fresh())]);
    }

    private function ownerOnly(Request $request, Gym $gym): void
    {
        abort_unless($request->user()->hasGymRole($gym, GymPersonType::Owner), 403, 'Only the gym owner can manage its subscription.');
    }

    /** @return array<string, mixed> */
    private function payload(Gym $gym): array
    {
        $state = Subscription::state($gym);

        return [
            'subscription' => [
                'status' => $state['status'],
                'plan' => $state['plan'] ? SubscriptionPlanController::payload($state['plan']) : null,
                'modules' => $state['modules'],
                'locked_modules' => $state['locked_modules'],
                'trial_ends_at' => $gym->trial_ends_at?->toIso8601String(),
                'paid_until' => $gym->paid_until?->toIso8601String(),
                'paid_plan_name' => $gym->paid_until && $gym->paid_until->isFuture() ? $gym->subscriptionPlan?->name : null,
                'period_ends_at' => Subscription::periodEnd($gym)?->toIso8601String(),
            ],
            'plans' => SubscriptionPlan::active()->orderByDesc('is_free')->orderBy('monthly_price')->get()
                ->map(fn (SubscriptionPlan $plan) => SubscriptionPlanController::payload($plan)),
            'modules' => collect(Subscription::MODULE_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'payments' => SubscriptionPayment::where('gym_id', $gym->id)->latest('id')->limit(24)->get()
                ->map(fn (SubscriptionPayment $payment) => [
                    'id' => $payment->id,
                    'plan_name' => $payment->plan_name,
                    'amount' => $payment->amount,
                    'period_starts_at' => $payment->period_starts_at->toIso8601String(),
                    'period_ends_at' => $payment->period_ends_at->toIso8601String(),
                    'paid_at' => $payment->created_at->toIso8601String(),
                ]),
        ];
    }
}
