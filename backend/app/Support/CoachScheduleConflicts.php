<?php

namespace App\Support;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Validation\ValidationException;

/**
 * R-117: a coach cannot offer hours that overlap their work schedule at another gym
 * where they are still employed. Reads every gym on purpose (withoutGlobalScope).
 */
final class CoachScheduleConflicts
{
    /** @param list<array{weekday: string, start: string, end: string}> $availability */
    public static function find(User $user, Gym $gym, array $availability): array
    {
        $activeGymIds = EmployeeProfile::withoutGlobalScope('gym')
            ->where('user_id', $user->id)->where('gym_id', '!=', $gym->id)->whereNull('ended_on')
            ->pluck('gym_id')
            // A removed employee with no end date no longer works there.
            ->filter(fn ($gymId) => $user->hasGymRole((int) $gymId, GymPersonType::Employee))->values();

        if ($activeGymIds->isEmpty()) {
            return [];
        }

        $schedules = WorkSchedule::withoutGlobalScope('gym')->with('gym:id,name')
            ->where('user_id', $user->id)->whereIn('gym_id', $activeGymIds)
            ->whereNotNull('start_time')->whereNotNull('end_time')->get();

        $conflicts = [];

        foreach ($availability as $slot) {
            foreach ($schedules->where('weekday', $slot['weekday']) as $shift) {
                $start = substr((string) $shift->start_time, 0, 5);
                $end = substr((string) $shift->end_time, 0, 5);

                if ($slot['start'] < $end && $start < $slot['end']) {
                    $day = config("hr.weekdays.{$slot['weekday']}");
                    $conflicts[] = "{$day} {$slot['start']}-{$slot['end']} overlaps your {$start}-{$end} shift at {$shift->gym?->name}.";
                }
            }
        }

        return $conflicts;
    }

    public static function assertNone(User $user, Gym $gym, array $availability): void
    {
        $conflicts = self::find($user, $gym, $availability);

        if ($conflicts !== []) {
            throw ValidationException::withMessages(['availability' => $conflicts]);
        }
    }
}
