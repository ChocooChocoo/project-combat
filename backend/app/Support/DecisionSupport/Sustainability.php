<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use App\Support\Finance\BudgetActual;
use App\Support\Payroll\Money;
use Illuminate\Support\Facades\DB;

final class Sustainability
{
    public function forPeriod(Gym $gym, Period $period): array
    {
        $business = new BusinessDashboards;
        $money = $business->money($gym, $period);
        $membership = (new MembershipMetrics)->forPeriod($gym, $period)['summary'];
        $services = (new TrainingDashboards)->services($gym, $period);
        $budget = (new BudgetActual)->forMonth($gym, substr($period->to(), 0, 7));
        $rows = array_map(fn ($row) => [...$row, 'percentage' => $row['percentage'] === null ? null : (float) $row['percentage']], $budget['budgets']);
        $totals = [];
        foreach (['income', 'expense'] as $kind) {
            $amounts = ['budget' => 0, 'actual' => 0, 'difference' => 0];
            foreach ($rows as $row) {
                if ($row['kind'] !== $kind) continue;
                foreach ($amounts as $field => $_) $amounts[$field] += Money::toCents($row[$field]);
            }
            $totals[] = ['kind' => $kind, ...array_map(fn ($cents) => Money::toDecimal($cents), $amounts),
                'percentage' => Metrics::rate($amounts['actual'], $amounts['budget'])];
        }
        $forecast = (new Forecast)->forPeriod($gym, $period, 'cash_flow');
        $hasData = count($services['classes']) > 0 || count($services['rooms']) > 0 || count($forecast['history']) > 0
            || count(array_filter($membership, fn ($value, $key) => ! str_ends_with($key, '_rate') && $value > 0, ARRAY_FILTER_USE_BOTH)) > 0
            || $period->apply(DB::table('payments')->where('gym_id', $gym->id), 'paid_at')->exists()
            || $period->apply(DB::table('expenses')->where('gym_id', $gym->id), 'incurred_on', false)->exists()
            || DB::table('budgets')->where('gym_id', $gym->id)->where('month', $budget['month'].'-01')->exists()
            || count(array_filter($rows, fn ($row) => Money::toCents($row['actual']) !== 0)) > 0;

        return ['has_data' => $hasData, 'financial' => $business->monthlyFinance($gym, $period),
            'revenue_mix' => array_intersect_key($money, array_flip(['income_types', 'session_services', 'service_allocation_anomalies'])),
            'membership' => $membership, 'utilization' => ['classes' => $services['classes'], 'rooms' => $services['rooms']],
            'budget' => ['month' => $budget['month'], 'basis' => 'whole_month_containing_range_end', 'rows' => $rows, 'totals' => $totals],
            'cash_flow_forecast' => $forecast];
    }
}
