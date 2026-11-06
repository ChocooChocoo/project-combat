<?php

namespace App\Support;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\DayKind;
use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * R-56: marks a working day absent when the employee has no row, no approved
 * leave and no holiday. Uses the same day classification as payroll (C-22).
 */
class StaffAbsenceMarker
{
    public function mark(Carbon $from, Carbon $to): int
    {
        $count = 0;

        foreach (Gym::where('status', GymStatus::Approved)->get() as $gym) {
            app(CurrentGym::class)->set($gym);

            $profiles = $gym->employees()->with('user')->get();

            for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
                foreach ($profiles as $profile) {
                    $user = $profile->user;

                    if (! $user) {
                        continue;
                    }

                    if ($profile->started_on && $profile->started_on->greaterThan($date)) {
                        continue;
                    }

                    if ($profile->ended_on && $profile->ended_on->lessThan($date)) {
                        continue;
                    }

                    // A removed employee with no end date must not accumulate absences forever.
                    if (! $profile->ended_on && ! $user->hasGymRole($gym, GymPersonType::Employee)) {
                        continue;
                    }

                    if (app(WorkingDays::class)->kind($gym, $user, $date) !== DayKind::Working) {
                        continue;
                    }

                    if ($this->onApprovedLeave($gym, $user, $date)) {
                        continue;
                    }

                    $exists = $gym->staffAttendance()
                        ->where('user_id', $user->id)
                        ->whereDate('work_date', $date->toDateString())
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $schedule = app(StaffAttendanceBook::class)->scheduleFor($gym, $user, $date);

                    $gym->staffAttendance()->create([
                        'user_id' => $user->id,
                        'work_date' => $date->toDateString(),
                        'scheduled_start' => $schedule['start'],
                        'scheduled_end' => $schedule['end'],
                        'status' => AttendanceStatus::Absent->value,
                        'source' => AttendanceSource::System->value,
                    ]);

                    $count++;
                }
            }

            $gym->staffAttendance()
                ->whereDate('work_date', '>=', $from->toDateString())
                ->whereDate('work_date', '<=', $to->toDateString())
                ->whereNotNull('clock_in_at')
                ->whereNull('clock_out_at')
                ->whereDate('work_date', '<', today()->toDateString())
                ->update(['missing_clock_out' => true]);
        }

        return $count;
    }

    private function onApprovedLeave(Gym $gym, User $user, Carbon $date): bool
    {
        return $gym->leaveRequests()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString())
            ->exists();
    }
}
