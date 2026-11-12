<?php

namespace App\Support\Reminders;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipReminder;
use App\Notifications\MembershipExpiryReminder;
use App\Support\CurrentGym;
use Illuminate\Support\Facades\DB;

class MembershipExpiryReminders
{
    public function send(): int
    {
        $sent = 0;
        Gym::where('status', GymStatus::Approved)->each(function (Gym $gym) use (&$sent) {
            app(CurrentGym::class)->set($gym);
            // R-115: each gym can choose its own reminder days.
            $thresholds = collect($gym->membership_reminder_days ?: config('communication.membership_expiry_reminder_days'))
                ->map(fn ($days) => (int) $days)->sort()->values();
            Membership::where('status', 'active')->whereNotNull('ends_on')
                ->whereDate('ends_on', '<=', today()->addDays($thresholds->max()))
                ->chunkById(100, function ($memberships) use (&$sent, $thresholds) {
                    foreach ($memberships as $membership) {
                        $sent += DB::transaction(function () use ($membership, $thresholds) {
                            $row = Membership::with(['user', 'plan'])->where('status', 'active')->whereNotNull('ends_on')
                                ->lockForUpdate()->find($membership->id);
                            if (! $row) {
                                return 0;
                            }
                            $daysLeft = (int) today()->startOfDay()->diffInDays($row->ends_on->copy()->startOfDay(), false);
                            if ($daysLeft < 0) {
                                return 0;
                            }
                            $threshold = $thresholds->first(fn ($days) => $daysLeft <= $days);
                            if ($threshold === null) {
                                return 0;
                            }
                            $inserted = MembershipReminder::insertOrIgnore([
                                'membership_id' => $row->id, 'ends_on' => $row->ends_on->toDateString(),
                                'days_before' => $threshold, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                            if (! $inserted) {
                                return 0;
                            }
                            $row->user->notify(new MembershipExpiryReminder($row, $daysLeft));

                            return 1;
                        });
                    }
                });
        });

        return $sent;
    }
}
