<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\Offer;
use Illuminate\Validation\ValidationException;

/**
 * R-111: picks and applies the offer for a membership's first charge, inside the approval transaction.
 * A voucher code wins when given; otherwise the best approved promo for the plan applies.
 * Only approved, in-date offers under their use limit count. Renewals never get a discount.
 */
final class OfferApplier
{
    /** Lowers amount_charged and records the offer. Call after the charge is set. */
    public function apply(Membership $membership, ?string $code): void
    {
        $charge = (float) $membership->amount_charged;
        $offer = $this->pick($membership, $code, $charge, true);

        if (! $offer) {
            return;
        }

        $discount = $offer->discountOn($charge);
        $offer->increment('uses');
        $membership->offer_id = $offer->id;
        $membership->discount_amount = $discount;
        $membership->amount_charged = round($charge - $discount, 2);
    }

    /** The offer that would apply to this charge. Read-only; `$lock` is for the approval transaction. */
    public function pick(Membership $membership, ?string $code, float $charge, bool $lock = false): ?Offer
    {
        if ($charge <= 0) {
            return null;
        }

        return filled($code) ? $this->voucher($membership, $code, $lock) : $this->bestPromo($membership, $charge, $lock);
    }

    private function voucher(Membership $membership, string $code, bool $lock): Offer
    {
        $offer = $this->live($membership, $lock)->where('kind', 'voucher')->where('code', strtoupper(trim($code)))->first();

        if (! $offer) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher code is not valid for this plan, has expired or is used up.']);
        }

        return $offer;
    }

    private function bestPromo(Membership $membership, float $charge, bool $lock): ?Offer
    {
        return $this->live($membership, $lock)->where('kind', 'promo')->get()
            ->sortByDesc(fn (Offer $offer) => $offer->discountOn($charge))
            ->first();
    }

    /** Usable offers for the plan, locked so two approvals cannot both take the last use. */
    private function live(Membership $membership, bool $lock)
    {
        $today = today()->toDateString();

        $query = Offer::query()
            ->where('gym_id', $membership->gym_id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
            ->whereHas('plans', fn ($query) => $query->whereKey($membership->membership_plan_id));

        return $lock ? $query->lockForUpdate() : $query;
    }
}
