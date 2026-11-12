<?php

namespace App\Support\Reminders;

use App\Enums\GymStatus;
use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Support\CurrentGym;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * R-116: active members with no gym visit and no attended session for the gym's inactivity days.
 * The member gets a notice and staff get one digest per gym. Notice only: nothing is blocked.
 * Each quiet spell is told once (inactivity_notices is unique per membership and last activity day).
 */
class InactivityMonitor
{
    public function run(): int
    {
        $sent = 0;

        Gym::where('status', GymStatus::Approved)->each(function (Gym $gym) use (&$sent) {
            app(CurrentGym::class)->set($gym);
            $cutoff = today()->subDays(max(1, (int) $gym->inactivity_days));
            $quiet = [];

            Membership::with('user')->where('status', MembershipStatus::Active->value)
                ->whereDate('starts_on', '<', $cutoff)
                ->chunkById(200, function ($memberships) use ($gym, $cutoff, &$quiet) {
                    foreach ($memberships as $membership) {
                        $last = $this->lastActivity($gym, $membership);

                        if ($last->greaterThanOrEqualTo($cutoff)) {
                            continue;
                        }

                        $inserted = DB::table('inactivity_notices')->insertOrIgnore([
                            'membership_id' => $membership->id, 'last_activity_on' => $last->toDateString(),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);

                        if (! $inserted) {
                            continue;
                        }

                        $days = (int) $last->diffInDays(today());
                        $membership->user->notify(new GymNotice(
                            'We miss you at '.$gym->name,
                            "Your last visit was {$days} days ago. Book a session or drop by when you can.",
                            $gym->id,
                            "/gyms/{$gym->id}/sessions",
                        ));
                        $quiet[] = trim("{$membership->user->first_name} {$membership->user->last_name}")." ({$days} days)";
                    }
                });

            if ($quiet === []) {
                return;
            }

            $sent += count($quiet);
            $list = implode(', ', array_slice($quiet, 0, 10)).(count($quiet) > 10 ? ' and '.(count($quiet) - 10).' more' : '');
            $gym->peopleWithPermission('members.memberships.view')->each(fn (User $person) => $person->notify(new GymNotice(
                count($quiet) === 1 ? '1 inactive member' : count($quiet).' inactive members',
                "No visit or attended session for {$gym->inactivity_days}+ days: {$list}.",
                $gym->id,
                "/gyms/{$gym->id}/manage/members",
            )));
        });

        return $sent;
    }

    /** Latest of: last check-in, last attended session, or the membership start. */
    private function lastActivity(Gym $gym, Membership $membership): Carbon
    {
        $visit = DB::table('gym_visits')->where('gym_id', $gym->id)->where('user_id', $membership->user_id)->max('checked_in_at');
        $session = DB::table('session_bookings')
            ->join('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
            ->where('session_bookings.gym_id', $gym->id)->where('session_bookings.user_id', $membership->user_id)
            ->whereIn('session_bookings.attendance', ['present', 'late'])
            ->max('training_sessions.starts_at');

        return collect([$visit, $session, $membership->starts_on?->toDateString()])
            ->filter()->map(fn ($value) => Carbon::parse($value)->startOfDay())->max();
    }
}
