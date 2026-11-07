<?php

namespace App\Support\Reminders;

use App\Enums\GymStatus;
use App\Models\CustomerFollowUp;
use App\Models\Gym;
use App\Notifications\FollowUpDueReminder;
use App\Support\CurrentGym;
use Illuminate\Support\Facades\DB;

class FollowUpReminders
{
    public function send(): int
    {
        $sent = 0;
        $now = now();

        Gym::where('status', GymStatus::Approved)->each(function (Gym $gym) use (&$sent, $now) {
            app(CurrentGym::class)->set($gym);
            $query = CustomerFollowUp::where('status', 'open')->whereNull('reminded_at')
                ->where('due_at', '<=', $now)
                ->where('due_at', '>=', $now->copy()->subHours(config('communication.follow_up_reminder_lookback_hours')));

            $query->chunkById(100, function ($followUps) use (&$sent, $query, $now) {
                foreach ($followUps as $followUp) {
                    $sent += DB::transaction(function () use ($query, $followUp, $now) {
                        $row = (clone $query)->with(['customer', 'assignedTo'])->lockForUpdate()->find($followUp->id);
                        if (! $row) {
                            return 0;
                        }
                        $row->assignedTo->notify(new FollowUpDueReminder($row));
                        $row->forceFill(['reminded_at' => $now])->save();

                        return 1;
                    });
                }
            });
        });

        return $sent;
    }
}
