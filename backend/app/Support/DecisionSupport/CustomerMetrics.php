<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class CustomerMetrics
{
    public function forPeriod(Gym $gym, Period $period): array
    {
        $leads = $period->apply(DB::table('customers as c')->where('c.gym_id', $gym->id), 'c.created_at');
        $linkedByEnd = fn (Builder $query) => $query->whereNull('c.linked_at')->orWhere('c.linked_at', '<', $period->endExclusive()->format('Y-m-d H:i:s'));
        $converted = (clone $leads)->where($linkedByEnd)->whereExists(function (Builder $membership) use ($gym, $period) {
            $membership->selectRaw('1')->from('memberships as m')->where('m.gym_id', $gym->id)->whereColumn('m.id', 'c.membership_id')
                ->whereExists(function (Builder $activation) use ($gym, $period) {
                    $period->apply($activation->selectRaw('1')->from('membership_events as a')->where('a.gym_id', $gym->id)
                        ->whereColumn('a.membership_id', 'm.id')->where('a.type', 'activated'), 'a.created_at');
                });
        });
        $sources = (clone $leads)->select('c.source')->selectRaw('COUNT(*) as leads')->groupBy('c.source')->orderBy('c.source')->get();
        $convertedSources = (clone $converted)->select('c.source')->selectRaw('COUNT(*) as count')->groupBy('c.source')->pluck('count', 'source');
        $gained = $period->apply((new MembershipMetrics)->firstActivations($gym), 'a.created_at')
            ->whereExists(function (Builder $customer) use ($gym, $linkedByEnd) {
                $customer->selectRaw('1')->from('customers as c')->where('c.gym_id', $gym->id)
                    ->whereColumn('c.membership_id', 'a.membership_id')->where($linkedByEnd);
            })->distinct()->count('m.user_id');
        $interactions = DB::table('customer_interactions as i')->where('i.gym_id', $gym->id)
            ->join('customers as c', fn (JoinClause $join) => $join->on('c.id', '=', 'i.customer_id')->where('c.gym_id', $gym->id));
        $concerns = (clone $interactions)->where('i.kind', 'concern');
        $leadCount = (clone $leads)->count();
        $convertedCount = (clone $converted)->count();

        return ['summary' => ['leads' => $leadCount, 'converted_leads' => $convertedCount,
            'conversion_rate' => Metrics::rate($convertedCount, $leadCount), 'members_gained' => $gained,
            'feedback' => $period->apply((clone $interactions)->where('i.kind', 'feedback'), 'i.occurred_at')->count(),
            'concerns_raised' => $period->apply(clone $concerns, 'i.occurred_at')->count(),
            'concerns_open_at_end' => (clone $concerns)->where('i.occurred_at', '<', $period->endExclusive()->format('Y-m-d H:i:s'))
                ->where(fn (Builder $query) => $query->whereNull('i.resolved_at')->orWhere('i.resolved_at', '>=', $period->endExclusive()->format('Y-m-d H:i:s')))->count(),
            'concerns_resolved' => $period->apply(clone $concerns, 'i.resolved_at')->count()],
            'sources' => $sources->map(fn ($row) => ['source' => $row->source, 'leads' => (int) $row->leads,
                'converted_leads' => (int) ($convertedSources[$row->source] ?? 0),
                'conversion_rate' => Metrics::rate((int) ($convertedSources[$row->source] ?? 0), (int) $row->leads)])->all()];
    }
}
