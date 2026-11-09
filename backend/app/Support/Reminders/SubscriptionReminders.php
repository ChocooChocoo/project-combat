<?php

namespace App\Support\Reminders;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\Gym;
use App\Notifications\SubscriptionEndingReminder;
use App\Support\Subscription;

/** R-107: one notice per period end, sent to the owners within the last few days. */
class SubscriptionReminders
{
    public const DAYS_BEFORE = 3;

    public function send(): int
    {
        $sent = 0;

        Gym::where('status', GymStatus::Approved)
            ->where(fn ($q) => $q->whereBetween('trial_ends_at', [now(), now()->addDays(self::DAYS_BEFORE)])
                ->orWhereBetween('paid_until', [now(), now()->addDays(self::DAYS_BEFORE)]))
            ->each(function (Gym $gym) use (&$sent) {
                $status = Subscription::state($gym)['status'];
                $endsAt = Subscription::periodEnd($gym);
                // A gym that already paid for the month after this one has nothing to renew yet.
                if (! $endsAt || $endsAt->gt(now()->addDays(self::DAYS_BEFORE)) || $gym->subscription_reminded_for?->eq($endsAt)) {
                    return;
                }
                $gym->people()->wherePivot('role', GymPersonType::Owner->value)->get()
                    ->each(fn ($owner) => $owner->notify(new SubscriptionEndingReminder($gym, $status, $endsAt)));
                $gym->forceFill(['subscription_reminded_for' => $endsAt])->save();
                $sent++;
            });

        return $sent;
    }
}
