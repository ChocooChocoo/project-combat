<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Models\Customer;
use App\Models\Gym;
use App\Models\Membership;

/**
 * R-71: links customers and membership applications in both directions.
 * Email matching is case-insensitive and always limited to one gym.
 */
class CustomerLinker
{
    /** The application waiting for this email, when nobody is linked to it yet. */
    public function membershipForEmail(Gym $gym, ?string $email): ?Membership
    {
        $email = $this->normalise($email);

        if ($email === null) {
            return null;
        }

        return Membership::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('status', [MembershipStatus::Applied->value, MembershipStatus::AwaitingPayment->value])
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(users.email) = ?', [$email]))
            ->whereDoesntHave('customers')
            ->orderBy('id')
            ->first();
    }

    /** The unlinked customer row waiting for this email. */
    public function customerForEmail(Gym $gym, ?string $email): ?Customer
    {
        $email = $this->normalise($email);

        if ($email === null) {
            return null;
        }

        return Customer::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereNull('membership_id')
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->first();
    }

    /** The one place that writes the link, so both directions record the same fields. */
    public function link(Customer $customer, Membership $membership): Customer
    {
        $customer->forceFill([
            'membership_id' => $membership->id,
            'linked_at' => now(),
        ]);

        if (! $customer->user_id) {
            $customer->user_id = $membership->user_id;
        }

        $customer->save();

        return $customer->refresh();
    }

    private function normalise(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }
}
