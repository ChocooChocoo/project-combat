<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use App\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MembershipMetrics
{
    public function activeIdsAt(Gym $gym, Carbon $boundary): Builder
    {
        $at = $boundary->format('Y-m-d H:i:s');

        return DB::table('memberships as m')->where('m.gym_id', $gym->id)->select('m.id')
            ->whereExists(function (Builder $activation) use ($gym, $at) {
                $activation->selectRaw('1')->from('membership_events as a')->where('a.gym_id', $gym->id)
                    ->whereColumn('a.membership_id', 'm.id')->where('a.type', 'activated')->where('a.created_at', '<', $at)
                    ->whereNotExists(function (Builder $later) use ($gym, $at) {
                        $later->selectRaw('1')->from('membership_events as z')->where('z.gym_id', $gym->id)
                            ->whereColumn('z.membership_id', 'm.id')->whereIn('z.type', ['activated', 'expired', 'cancelled'])
                            ->where('z.created_at', '<', $at)->where(fn (Builder $order) => $order->whereColumn('z.created_at', '>', 'a.created_at')
                                ->orWhere(fn (Builder $tie) => $tie->whereColumn('z.created_at', 'a.created_at')->whereColumn('z.id', '>', 'a.id')));
                    });
            });
    }

    public function firstActivations(Gym $gym): Builder
    {
        return DB::table('membership_events as a')->where('a.gym_id', $gym->id)->where('a.type', 'activated')
            ->join('memberships as m', fn (JoinClause $join) => $join->on('m.id', '=', 'a.membership_id')->where('m.gym_id', $gym->id))
            ->select('a.id', 'a.membership_id', 'a.created_at', 'm.user_id')
            ->whereNotExists(function (Builder $earlier) use ($gym) {
                $earlier->selectRaw('1')->from('membership_events as prior')->where('prior.gym_id', $gym->id)
                    ->whereColumn('prior.membership_id', 'a.membership_id')->where('prior.type', 'activated')
                    ->where(fn (Builder $order) => $order->whereColumn('prior.created_at', '<', 'a.created_at')
                        ->orWhere(fn (Builder $tie) => $tie->whereColumn('prior.created_at', 'a.created_at')->whereColumn('prior.id', '<', 'a.id')));
            });
    }

    public function forPeriod(Gym $gym, Period $period): array
    {
        $opening = $this->activeIdsAt($gym, $period->start());
        $activeAtStart = $opening->count();
        $events = $period->apply(DB::table('membership_events as e')->where('e.gym_id', $gym->id)
            ->join('memberships as m', fn (JoinClause $join) => $join->on('m.id', '=', 'e.membership_id')->where('m.gym_id', $gym->id)), 'e.created_at');
        $churn = (clone $events)->whereIn('e.type', ['expired', 'cancelled'])->whereIn('e.membership_id', $opening)->distinct()->count('e.membership_id');
        $new = $period->apply($this->firstActivations($gym), 'a.created_at');
        $activity = (clone $events)->whereIn('e.type', ['renewed', 'expired', 'cancelled'])
            ->select('e.type')->selectRaw(Sql::month('e.created_at').' as period, COUNT(*) as count')
            ->groupBy('e.type')->groupByRaw(Sql::month('e.created_at'))->get();
        $newMonths = (clone $new)->select(DB::raw(Sql::month('a.created_at').' as period'), DB::raw('COUNT(*) as count'))
            ->groupByRaw(Sql::month('a.created_at'))->get();
        $summary = ['new' => (clone $new)->count(), 'renewed' => 0, 'expired' => 0, 'cancelled' => 0];
        $buckets = [];
        foreach ($period->monthKeys() as $month) $buckets[$month] = ['period' => $month, 'new' => 0, 'renewed' => 0, 'expired' => 0, 'cancelled' => 0];
        foreach ($activity as $row) {
            $summary[$row->type] += (int) $row->count;
            $buckets[$row->period][$row->type] = (int) $row->count;
        }
        foreach ($newMonths as $row) $buckets[$row->period]['new'] = (int) $row->count;
        $missing = DB::table('memberships as m')->where('m.gym_id', $gym->id)->whereIn('m.status', ['active', 'frozen'])
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('membership_events as a')
                ->where('a.gym_id', $gym->id)->whereColumn('a.membership_id', 'm.id')->where('a.type', 'activated'))->count();

        return ['summary' => [...$summary, 'active_at_start' => $activeAtStart, 'churned_from_start' => $churn,
            'retained_from_start' => $activeAtStart - $churn, 'active_at_end' => $this->activeIdsAt($gym, $period->endExclusive())->count(),
            'retention_rate' => Metrics::rate($activeAtStart - $churn, $activeAtStart),
            'renewal_rate' => Metrics::rate($summary['renewed'], $summary['renewed'] + $summary['expired']),
            'missing_activation_history' => $missing], 'buckets' => array_values($buckets)];
    }
}
