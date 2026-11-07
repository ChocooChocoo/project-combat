<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class Trends
{
    public function forPeriod(Gym $gym, Period $period): array
    {
        $months = config('decision_support.trends.months');
        $window = $period->trailingMonths($months, false);
        $sessions = (new TrainingDashboards)->sessions($gym, $window);
        $membership = new MembershipMetrics;
        $activity = $membership->forPeriod($gym, $window);
        $byMonth = collect($activity['buckets'])->keyBy('period');
        $finance = (new BusinessDashboards)->monthlyFinance($gym, $window);
        $income = collect($finance['buckets'])->keyBy('period');
        $attendance = $sessions->groupBy(fn ($row) => substr($row['starts_at'], 0, 7));
        $buckets = [];
        foreach ($window->monthKeys() as $month) {
            $rows = $attendance[$month] ?? collect();
            $attended = (int) $rows->sum('attended');
            $marked = (int) $rows->sum('marked');
            $boundary = Carbon::parse($month.'-01')->addMonth()->min($window->endExclusive());
            $buckets[] = ['period' => $month, 'attended' => $attended, 'marked' => $marked, 'attendance_rate' => Metrics::rate($attended, $marked),
                ...array_intersect_key($byMonth[$month], array_flip(['new', 'renewed', 'expired', 'cancelled'])),
                'active_at_end' => $membership->activeIdsAt($gym, $boundary)->count(), 'revenue' => $income[$month]['income']];
        }
        $end = $window->endExclusive()->toDateTimeString();
        $firstAttendance = DB::table('training_sessions as s')->where('s.gym_id', $gym->id)->where('s.starts_at', '<', $end)
            ->join('session_bookings as b', fn (JoinClause $join) => $join->on('b.training_session_id', '=', 's.id')->where('b.gym_id', $gym->id))
            ->whereIn('b.attendance', ['present', 'late', 'absent', 'no_show'])->min('s.starts_at');
        $firstMembership = DB::table('membership_events as e')->where('e.gym_id', $gym->id)->where('e.created_at', '<', $end)
            ->join('memberships as m', fn (JoinClause $join) => $join->on('m.id', '=', 'e.membership_id')->where('m.gym_id', $gym->id))
            ->whereIn('e.type', ['activated', 'renewed', 'expired', 'cancelled'])->min('e.created_at');
        $firstRevenue = DB::table('payments')->where('gym_id', $gym->id)->where('paid_at', '<', $end)->min('paid_at');
        $date = fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString();
        $attended = (int) $sessions->sum('attended');
        $marked = (int) $sessions->sum('marked');

        return ['window' => ['from' => $window->from(), 'to' => $window->to(), 'months' => $months, 'partial_final_month' => ! Carbon::parse($window->to())->isLastOfMonth()],
            'first_records' => ['attendance' => $date($firstAttendance), 'memberships' => $date($firstMembership), 'revenue' => $date($firstRevenue)],
            'buckets' => $buckets, 'totals' => ['attended' => $attended, 'marked' => $marked, 'attendance_rate' => Metrics::rate($attended, $marked),
                ...array_intersect_key($activity['summary'], array_flip(['new', 'renewed', 'expired', 'cancelled'])), 'revenue' => $finance['totals']['income']],
            'closing_active' => $buckets[count($buckets) - 1]['active_at_end']];
    }
}
