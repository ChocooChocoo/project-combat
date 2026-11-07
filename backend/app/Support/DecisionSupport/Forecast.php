<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use App\Support\Payroll\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class Forecast
{
    public function forPeriod(Gym $gym, Period $period, string $basis = 'income_expense'): array
    {
        if (! in_array($basis, ['income_expense', 'cash_flow'], true)) throw new \InvalidArgumentException('Unknown forecast basis.');
        $window = $period->trailingMonths(config('decision_support.forecast.history_months'), true);
        $end = $window->endExclusive()->toDateTimeString();
        $dates = [DB::table('payments')->where('gym_id', $gym->id)->where('paid_at', '<', $end)->min('paid_at')];
        $expenses = DB::table('expenses')->where('gym_id', $gym->id)->where('incurred_on', '<', $window->endExclusive()->toDateString());
        if ($basis === 'cash_flow') {
            $expenses->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', 'payroll_run'));
            $dates[] = DB::table('supplier_payments')->where('gym_id', $gym->id)->where('paid_on', '<', $window->endExclusive()->toDateString())->min('paid_on');
        }
        $dates[] = $expenses->min('incurred_on');
        $dates = array_values(array_filter($dates, fn ($date) => $date !== null));
        $history = [];
        $from = null;
        if ($dates) {
            $first = Carbon::parse(min($dates))->startOfMonth();
            $from = $first->max($window->start())->toDateString();
            $report = (new BusinessDashboards)->monthlyFinance($gym, new Period($from, $window->to()), $basis);
            $history = $basis === 'cash_flow' ? array_map(fn ($row) => ['period' => $row['period'], 'income' => $row['in'], 'expenses' => $row['out'], 'net' => $row['net']], $report['buckets']) : $report['buckets'];
        }
        $anchor = Carbon::parse($period->to())->min(Carbon::today('Asia/Manila'));

        return ['basis' => $basis,
            'series_labels' => $basis === 'cash_flow' ? ['income' => 'Cash in', 'expenses' => 'Cash out', 'net' => 'Cash-flow net'] : ['income' => 'Income', 'expenses' => 'Expenses', 'net' => 'Income less expenses'],
            'window' => ['from' => $from, 'to' => $window->to(), 'months' => count($history),
                'excluded_partial_month' => $anchor->format('Y-m') === substr($window->to(), 0, 7) ? null : $anchor->format('Y-m')],
            'history' => $history, ...$this->project($history)];
    }

    public function project(array $monthlyBuckets): array
    {
        $n = count($monthlyBuckets);
        $trend = $n >= config('decision_support.forecast.minimum_trend_months');
        $method = $n === 0 ? 'no_history' : ($trend ? 'least_squares' : 'average');
        $result = ['method' => $method, 'method_name' => match ($method) {
            'least_squares' => 'Straight-line least-squares trend', 'average' => 'average, not enough history', default => 'No history',
        }, 'fit' => ['income_r_squared' => null, 'expense_r_squared' => null], 'buckets' => [],
            'totals' => ['income' => '0.00', 'expenses' => '0.00', 'net' => '0.00']];
        if ($n === 0) return $result;
        $incoming = $this->fit(array_map(fn ($row) => Money::toCents($row['income']), $monthlyBuckets), $trend);
        $outgoing = $this->fit(array_map(fn ($row) => Money::toCents($row['expenses']), $monthlyBuckets), $trend);
        $result['fit'] = ['income_r_squared' => $incoming['r_squared'], 'expense_r_squared' => $outgoing['r_squared']];
        $lastMonth = Carbon::parse($monthlyBuckets[$n - 1]['period'].'-01');
        $totals = ['income' => 0, 'expenses' => 0, 'net' => 0];
        for ($k = 0; $k < config('decision_support.forecast.ahead_months'); $k++) {
            $income = max(0, Money::roundHalfUp($incoming['intercept'] + $incoming['slope'] * ($n + $k)));
            $expenses = max(0, Money::roundHalfUp($outgoing['intercept'] + $outgoing['slope'] * ($n + $k)));
            $amounts = ['income' => $income, 'expenses' => $expenses, 'net' => $income - $expenses];
            foreach ($amounts as $key => $amount) $totals[$key] += $amount;
            $result['buckets'][] = ['period' => $lastMonth->copy()->addMonths($k + 1)->format('Y-m'), ...array_map(fn ($cents) => Money::toDecimal($cents), $amounts)];
        }
        $result['totals'] = array_map(fn ($cents) => Money::toDecimal($cents), $totals);

        return $result;
    }

    private function fit(array $values, bool $trend): array
    {
        $n = count($values);
        $mean = array_sum($values) / $n;
        if (! $trend) return ['intercept' => $mean, 'slope' => 0, 'r_squared' => null];
        $xMean = ($n - 1) / 2;
        $covariance = 0; $variance = 0;
        foreach ($values as $x => $value) {
            $covariance += ($x - $xMean) * ($value - $mean);
            $variance += ($x - $xMean) ** 2;
        }
        $slope = $covariance / $variance;
        $intercept = $mean - $slope * $xMean;
        $sse = 0; $sst = 0;
        foreach ($values as $x => $value) {
            $sse += ($value - ($intercept + $slope * $x)) ** 2;
            $sst += ($value - $mean) ** 2;
        }

        return ['intercept' => $intercept, 'slope' => $slope,
            'r_squared' => round(max(0, min(1, $sst == 0 ? 1 : 1 - $sse / $sst)), 2)];
    }
}
