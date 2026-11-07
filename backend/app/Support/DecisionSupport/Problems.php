<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class Problems
{
    public function forPeriod(Gym $gym, Period $period): array
    {
        $sessions = app(TrainingDashboards::class)->sessions($gym, $period)
            ->where('status', 'scheduled')->filter(fn ($row) => Carbon::parse($row['ends_at'])->lt(now()));
        $thresholds = config('decision_support');
        $items = [];
        $falling = $thresholds['falling_attendance'];
        $noShow = $thresholds['high_no_show'];
        foreach ($sessions->groupBy('class_key') as $key => $rows) {
            $window = $falling['window_sessions'];
            $latest = $rows->take(-2 * $window)->values();
            if ($latest->count() === 2 * $window) {
                $previous = $this->attendanceWindow($latest->take($window));
                $current = $this->attendanceWindow($latest->slice($window));
                if ($previous['marked'] > 0 && $current['marked'] > 0) {
                    $previousRate = 100 * $previous['attended'] / $previous['marked'];
                    $currentRate = 100 * $current['attended'] / $current['marked'];
                    $dropNumerator = 100 * ($previous['attended'] * $current['marked'] - $current['attended'] * $previous['marked']);
                    $dropDenominator = $previous['marked'] * $current['marked'];
                    if ($dropNumerator >= $falling['drop_points'] * $dropDenominator
                        && 100 * $current['attended'] < $falling['current_below'] * $current['marked']) {
                        $items[] = ['code' => 'falling_attendance', 'key' => $key, 'name' => $rows->first()['name'],
                            'evidence' => ['previous' => $previous, 'current' => $current,
                                'drop_points' => round($previousRate - $currentRate, 2), 'session_ids' => $latest->pluck('id')->all()]];
                    }
                }
            }
            $marked = (int) $rows->sum('marked');
            $missed = (int) $rows->sum('no_show');
            if ($marked >= $noShow['minimum_expected'] && $marked > 0 && 100 * $missed / $marked >= $noShow['rate_at_least']) {
                $items[] = ['code' => 'high_no_show', 'key' => $key, 'name' => $rows->first()['name'],
                    'evidence' => ['from' => $period->from(), 'to' => $period->to(), 'marked' => $marked,
                        'no_show' => $missed, 'awaiting_attendance' => (int) $rows->sum('awaiting_attendance'),
                        'rate' => Metrics::rate($missed, $marked)]];
            }
        }
        $slot = $thresholds['low_use_slot'];
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($sessions->where('capacity', '>', 0)->groupBy(fn ($row) => $row['weekday'].':'.$row['hour']) as $key => $rows) {
            $mean = $rows->avg(fn ($row) => 100 * $row['held_seats'] / $row['capacity']);
            if ($rows->count() >= $slot['minimum_sessions'] && $mean < $slot['fill_below']) {
                $first = $rows->first();
                $items[] = ['code' => 'low_use_slot', 'key' => $key,
                    'name' => $days[$first['weekday']].sprintf(' %02d:00', $first['hour']),
                    'evidence' => ['weekday' => $first['weekday'], 'hour' => $first['hour'], 'sessions' => $rows->count(),
                        'held_seats' => (int) $rows->sum('held_seats'), 'capacity' => (int) $rows->sum('capacity'),
                        'average_fill' => round($mean, 2), 'session_ids' => $rows->pluck('id')->values()->all()]];
            }
        }
        $summary = array_fill_keys(['falling_attendance', 'low_use_slot', 'high_no_show'], 0);
        foreach ($items as $item) $summary[$item['code']]++;

        return ['has_data' => $sessions->isNotEmpty(), 'thresholds' => $thresholds,
            'summary' => [...$summary, 'total' => count($items)], 'items' => $items];
    }

    private function attendanceWindow(Collection $rows): array
    {
        $attended = (int) $rows->sum('attended');
        $marked = (int) $rows->sum('marked');

        return ['from' => substr($rows->first()['starts_at'], 0, 10), 'to' => substr($rows->last()['starts_at'], 0, 10),
            'attended' => $attended, 'marked' => $marked, 'rate' => Metrics::rate($attended, $marked)];
    }
}
