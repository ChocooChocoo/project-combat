<?php

namespace App\Support;

use App\Enums\DayKind;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;

/**
 * Counts the days someone actually works between two dates (R-24, R-26, R-27).
 * An employee's own timed schedule wins; otherwise the gym's working days apply.
 * National and gym holidays never count.
 */
class WorkingDays
{
    public function count(Gym $gym, User $employee, Carbon $from, Carbon $to): int
    {
        $total = 0;

        for ($year = $from->year; $year <= $to->year; $year++) {
            $total += $this->countInYear($gym, $employee, $from, $to, $year);
        }

        return $total;
    }

    /** The part of the range that falls inside one calendar year. */
    public function countInYear(Gym $gym, User $employee, Carbon $from, Carbon $to, int $year): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();

        if ($start->lessThan($yearStart)) {
            $start = $yearStart;
        }

        if ($end->greaterThan($yearEnd)) {
            $end = $yearEnd;
        }

        if ($start->greaterThan($end)) {
            return 0;
        }

        $workingDays = $this->workingDays($gym, $employee);
        $holidays = $this->holidays($gym, $year, $start, $end);

        $count = 0;
        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            $weekday = strtolower($day->format('D'));

            if (in_array($weekday, $workingDays, true) && ! isset($holidays[$day->toDateString()])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The one classification used by staff attendance, the absence job and payroll.
     * Holidays win over the schedule; a gym holiday counts as a special holiday.
     */
    public function kind(Gym $gym, User $employee, Carbon $date): DayKind
    {
        $day = $date->copy()->startOfDay();

        $national = collect(NationalHolidays::forYear($day->year))->firstWhere('date', $day->toDateString());
        if ($national) {
            return ($national['kind'] ?? 'special') === 'regular'
                ? DayKind::HolidayRegular
                : DayKind::HolidaySpecial;
        }

        $gymHoliday = GymHoliday::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereDate('date', $day->toDateString())
            ->exists();

        if ($gymHoliday) {
            return DayKind::HolidaySpecial;
        }

        $weekday = strtolower($day->format('D'));

        return in_array($weekday, $this->workingDays($gym, $employee), true)
            ? DayKind::Working
            : DayKind::Rest;
    }

    /** @return array<int, string> */
    private function workingDays(Gym $gym, User $employee): array
    {
        $scheduled = WorkSchedule::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->where('user_id', $employee->id)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->pluck('weekday')
            ->unique()
            ->values()
            ->all();

        return $scheduled ?: $gym->workingDays();
    }

    /** @return array<string, true> Dates that never count as working days. */
    private function holidays(Gym $gym, int $year, Carbon $start, Carbon $end): array
    {
        $dates = array_column(NationalHolidays::forYear($year), 'date');

        $gymHolidays = GymHoliday::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        return array_fill_keys([...$dates, ...$gymHolidays], true);
    }
}
