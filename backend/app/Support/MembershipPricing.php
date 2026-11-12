<?php

namespace App\Support;

use App\Models\MembershipPlan;

/**
 * R-109: the one place for membership price math.
 * Prepaying several terms of a time plan charges the locked fee per term, less the plan's prepay discount.
 */
final class MembershipPricing
{
    public static function charge(MembershipPlan $plan, float $lockedFee, int $terms = 1): float
    {
        $percent = $terms > 1 ? (float) $plan->prepay_discount_percent : 0.0;

        return round($lockedFee * $terms * (1 - $percent / 100), 2);
    }

    /** Months one payment covers. */
    public static function months(MembershipPlan $plan, int $terms = 1): int
    {
        return (int) $plan->duration_months * max(1, $terms);
    }

    /** Validation rule for a requested number of terms on this plan. */
    public static function termsRule(MembershipPlan $plan): array
    {
        return ['sometimes', 'integer', 'min:1', 'max:'.max(1, (int) $plan->max_prepay_terms)];
    }
}
