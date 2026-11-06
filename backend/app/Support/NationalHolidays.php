<?php

namespace App\Support;

/** Builds the Philippine national holiday list for one year (R-27). Nothing is stored. */
class NationalHolidays
{
    /**
     * @return array<int, array{date: string, name: string, kind: string}>
     */
    public static function forYear(int $year): array
    {
        $holidays = [];

        foreach (config('holidays.fixed', []) as $holiday) {
            $holidays[] = [
                'date' => sprintf('%04d-%02d-%02d', $year, $holiday['month'], $holiday['day']),
                'name' => $holiday['name'],
                'kind' => $holiday['kind'] ?? 'special',
            ];
        }

        foreach (config("holidays.years.{$year}", []) as $holiday) {
            $holidays[] = [
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'kind' => $holiday['kind'] ?? 'special',
            ];
        }

        // A date can appear twice; the calendar shows it once.
        $byDate = [];
        foreach ($holidays as $holiday) {
            $byDate[$holiday['date']] = ['name' => $holiday['name'], 'kind' => $holiday['kind']];
        }
        ksort($byDate);

        $list = [];
        foreach ($byDate as $date => $holiday) {
            $list[] = ['date' => $date, 'name' => $holiday['name'], 'kind' => $holiday['kind']];
        }

        return $list;
    }
}
