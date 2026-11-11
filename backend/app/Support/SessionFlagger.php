<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Models\Gym;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\SessionChanged;
use App\Notifications\SessionsFlagged;
use Illuminate\Database\Eloquent\Builder;

/**
 * R-46, D-32: marks upcoming sessions whose coach can no longer run them, tells the booked
 * athletes and sends one summary to session managers. Callers hold the gym lock.
 */
class SessionFlagger
{
    /**
     * @param  Builder<TrainingSession>  $sessions
     * @param  string|callable(TrainingSession): ?string  $reason  null from the callable skips that session
     */
    public function flag(Gym $gym, Builder $sessions, string|callable $reason, string $summary): int
    {
        $flagged = 0;
        $upcoming = $sessions->where('status', SessionStatus::Scheduled)->whereNull('flagged_at')
            ->where('starts_at', '>', now())->lockForUpdate()->get();
        foreach ($upcoming as $session) {
            $why = is_string($reason) ? $reason : $reason($session);
            if ($why === null) {
                continue;
            }
            $session->update(['flagged_at' => now(), 'flag_reason' => $why]);
            $this->tellAthletes($session, 'flagged', "This session may change: {$why}. The gym will update you.");
            $flagged++;
        }
        if ($flagged > 0) {
            foreach ($gym->peopleWithPermission('training.sessions.manage') as $manager) {
                $manager->notify(new SessionsFlagged($gym->id, $flagged, $summary));
            }
        }

        return $flagged;
    }

    public function tellAthletes(TrainingSession $session, string $type, string $message): void
    {
        $ids = $session->bookings()->whereIn('status', [BookingStatus::Requested, BookingStatus::Booked, BookingStatus::Waitlisted])->pluck('user_id');
        foreach (User::whereIn('id', $ids)->get() as $user) {
            $user->notify(new SessionChanged($session, $type, $message));
        }
    }
}
