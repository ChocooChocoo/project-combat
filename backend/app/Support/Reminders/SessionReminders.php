<?php

namespace App\Support\Reminders;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\SessionBooking;
use App\Notifications\SessionReminder;
use App\Support\CurrentGym;
use Illuminate\Support\Facades\DB;

class SessionReminders
{
    public function send(): int
    {
        $sent = 0;
        $now = now();

        Gym::where('status', GymStatus::Approved)->each(function (Gym $gym) use (&$sent, $now) {
            app(CurrentGym::class)->set($gym);
            $query = SessionBooking::where('status', 'booked')->whereNull('reminder_sent_at')
                ->whereHas('session', fn ($query) => $query->where('status', 'scheduled')
                    ->where('starts_at', '>', $now)->where('starts_at', '<=', $now->copy()->addMinutes($gym->session_reminder_minutes)));

            $query->chunkById(100, function ($bookings) use (&$sent, $query, $now) {
                foreach ($bookings as $booking) {
                    $sent += DB::transaction(function () use ($query, $booking, $now) {
                        $row = (clone $query)->with(['user', 'session.room'])->lockForUpdate()->find($booking->id);
                        if (! $row) {
                            return 0;
                        }
                        $row->user->notify(new SessionReminder($row));
                        $row->forceFill(['reminder_sent_at' => $now])->save();

                        return 1;
                    });
                }
            });
        });

        return $sent;
    }
}
