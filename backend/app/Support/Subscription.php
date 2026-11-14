<?php

namespace App\Support;

use App\Models\Gym;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

/**
 * What a gym's platform plan allows (R-102 to R-106).
 *
 * - Trial (until trial_ends_at): every module (Q-25).
 * - Paid (until paid_until): the paid plan's modules.
 * - Otherwise: the free plan's modules (Q-27: no extra days).
 *
 * The state is worked out on every check, so nothing has to run when a period ends.
 * A gym with no trial date was never put on a plan (old rows, test fixtures) and is not limited.
 */
final class Subscription
{
    /** Owner-side modules a plan can switch off. Gym setup stays open so an owner can always reach their gym. */
    public const MODULE_LABELS = [
        'hr' => 'Staff and HR',
        'training' => 'Training',
        'members' => 'Members',
        'health_records' => 'Health records',
        'finance' => 'Finance',
        'payroll' => 'Payroll',
        'customers' => 'Customers',
        'inventory' => 'Stock',
        'procurement' => 'Purchasing',
        'announcements' => 'Announcements',
        'decision_support' => 'Analytics',
    ];

    public const DEFAULT_TRIAL_DAYS = 30;

    /** @return list<string> */
    public static function lockableModules(): array
    {
        return array_keys(self::MODULE_LABELS);
    }

    public static function trialDays(): int
    {
        return (int) PlatformSetting::get('trial_days', self::DEFAULT_TRIAL_DAYS);
    }

    public static function freePlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('is_free', true)->first();
    }

    /** Q-26: the trial starts when the gym is approved. A gym gets one trial only. */
    public static function startTrial(Gym $gym): void
    {
        if ($gym->trial_ends_at === null) {
            $gym->trial_ends_at = now()->addDays(self::trialDays());
        }
    }

    /**
     * @return array{status: string, plan: ?SubscriptionPlan, modules: list<string>, locked_modules: list<string>}
     *               status is unmanaged, trial, paid or free.
     */
    public static function state(Gym $gym): array
    {
        $all = self::lockableModules();

        if ($gym->trial_ends_at === null) {
            return ['status' => 'unmanaged', 'plan' => null, 'modules' => $all, 'locked_modules' => []];
        }

        if (now()->lt($gym->trial_ends_at)) {
            return ['status' => 'trial', 'plan' => null, 'modules' => $all, 'locked_modules' => []];
        }

        $paid = $gym->paid_until && now()->lt($gym->paid_until) ? $gym->subscriptionPlan : null;
        $plan = $paid ?? self::freePlan();
        $modules = array_values(array_intersect($all, $plan?->modules ?? []));

        return [
            'status' => $paid ? 'paid' : 'free',
            'plan' => $plan,
            'modules' => $modules,
            'locked_modules' => array_values(array_diff($all, $modules)),
        ];
    }

    /** @return list<string> */
    public static function lockedModules(Gym $gym): array
    {
        return self::state($gym)['locked_modules'];
    }

    /** When the current trial or paid period ends; null on the free plan or when not managed. */
    public static function periodEnd(Gym $gym): ?\Illuminate\Support\Carbon
    {
        return match (self::state($gym)['status']) {
            'trial' => $gym->trial_ends_at,
            'paid' => $gym->paid_until,
            default => null,
        };
    }

    /**
     * Records a PayMongo-confirmed payment once; the unique checkout id stops the webhook and
     * the return page from both recording it. The new month starts after any time already
     * paid for or still on trial.
     */
    public static function recordCheckout(Gym $gym, string $checkoutId, array $result, ?int $paidBy = null): void
    {
        DB::transaction(function () use ($gym, $checkoutId, $result, $paidBy) {
            $locked = Gym::lockForUpdate()->find($gym->id);

            if (SubscriptionPayment::where('paymongo_checkout_id', $checkoutId)->exists()) {
                return;
            }

            $plan = SubscriptionPlan::find($locked->pending_subscription_checkout_id === $checkoutId
                ? $locked->pending_subscription_plan_id
                : null);
            if (! $plan) {
                return;
            }

            // ponytail: switching plans carries the time left over to the new plan; prorate if pricing needs it.
            $start = collect([now(), $locked->paid_until, $locked->trial_ends_at])->filter()->max();
            $end = $start->copy()->addMonthNoOverflow();

            SubscriptionPayment::create([
                'gym_id' => $locked->id,
                'subscription_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => (float) $result['amount'],
                'paymongo_checkout_id' => $checkoutId,
                'paymongo_payment_id' => $result['payment_id'] ?? null,
                'period_starts_at' => $start,
                'period_ends_at' => $end,
                'paid_by' => $paidBy,
            ]);

            $locked->forceFill([
                'subscription_plan_id' => $plan->id,
                'paid_until' => $end,
                'pending_subscription_checkout_id' => null,
                'pending_subscription_plan_id' => null,
                'subscription_reminded_for' => null,
            ])->save();
        });
    }
}
