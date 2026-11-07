<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use App\Models\StockItem;
use App\Support\Finance\Receivables;
use App\Support\Finance\Reports;
use App\Support\IncomeQuery;
use App\Support\Payroll\Money;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BusinessDashboards
{
    public function employees(Gym $gym, Period $period, bool $payVisible): array
    {
        $attendance = $period->apply(DB::table('staff_attendance as a')->where('a.gym_id', $gym->id), 'a.work_date', false)
            ->join('users as u', 'u.id', '=', 'a.user_id')->select('a.user_id', 'u.first_name', 'u.last_name')
            ->groupBy('a.user_id', 'u.first_name', 'u.last_name')->orderBy('u.last_name')->orderBy('a.user_id');
        foreach (['present', 'late', 'absent'] as $status) $attendance->selectRaw("SUM(CASE WHEN a.status = ? THEN 1 ELSE 0 END) as {$status}", [$status]);
        foreach (['worked_minutes', 'late_minutes', 'undertime_minutes', 'missing_clock_out'] as $field) $attendance->selectRaw("SUM(a.{$field}) as {$field}");
        $rows = $attendance->get()->map(function ($row) {
            $counts = [];
            foreach (['present', 'late', 'absent', 'worked_minutes', 'late_minutes', 'undertime_minutes', 'missing_clock_out'] as $key) $counts[$key] = (int) $row->$key;

            return ['user_id' => (int) $row->user_id, 'name' => trim($row->first_name.' '.$row->last_name), ...$this->staffRates($counts)];
        });
        $summary = [];
        foreach (['present', 'late', 'absent', 'worked_minutes', 'late_minutes', 'undertime_minutes', 'missing_clock_out'] as $key) $summary[$key] = (int) $rows->sum($key);
        $data = ['pay_visible' => $payVisible, 'summary' => $this->staffRates($summary), 'attendance' => $rows->all(),
            'workload' => (new TrainingDashboards)->services($gym, $period)['coaches'],
            'payroll' => (new Reports)->payrollSummary($gym, $period->from(), $period->to())];
        if ($payVisible) {
            $data['payroll_by_employee'] = $period->apply(DB::table('payroll_lines as l')->where('l.gym_id', $gym->id)
                ->join('payroll_runs as r', fn (JoinClause $join) => $join->on('r.id', '=', 'l.payroll_run_id')->where('r.gym_id', $gym->id))
                ->where('r.status', 'released')->join('users as u', 'u.id', '=', 'l.user_id'), 'r.ends_on', false)
                ->select('l.user_id', 'u.first_name', 'u.last_name')->selectRaw('SUM(l.gross_pay) as gross, SUM(l.net_pay) as net, COUNT(*) as line_count')
                ->selectRaw('SUM(l.sss_employer + l.ec_employer + l.philhealth_employer + l.pagibig_employer) as employer_statutory')
                ->groupBy('l.user_id', 'u.first_name', 'u.last_name')->orderBy('l.user_id')->get()->map(fn ($row) => [
                    'user_id' => (int) $row->user_id, 'name' => trim($row->first_name.' '.$row->last_name),
                    'gross' => Money::toDecimal(Money::toCents($row->gross)), 'net' => Money::toDecimal(Money::toCents($row->net)),
                    'employer_statutory' => Money::toDecimal(Money::toCents($row->employer_statutory)), 'line_count' => (int) $row->line_count,
                ])->all();
        }

        return $data;
    }

    public function money(Gym $gym, Period $period): array
    {
        $reports = new Reports;
        $incomeExpense = $reports->incomeAgainstExpenses($gym, $period->from(), $period->to());
        $total = Money::toCents($incomeExpense['totals']['income']);
        $income = new IncomeQuery;
        $types = $income->totals($gym, $period->from(), $period->to())->keyBy('type');
        $mix = [];
        foreach (array_keys(config('finance.income_types')) as $type) {
            $amount = $types[$type]['amount'] ?? '0.00';
            $mix[] = ['type' => $type, 'amount' => $amount, 'share' => $total > 0 ? Metrics::rate(Money::toCents($amount), $total) : null];
        }

        return ['income_expense' => $incomeExpense, 'cash_flow' => $reports->cashFlow($gym, $period->from(), $period->to()),
            'income_types' => $mix, 'session_services' => $income->serviceTotals($gym, $period->from(), $period->to())->all(),
            'service_allocation_anomalies' => $period->apply(DB::table('payments')->where('gym_id', $gym->id)
                ->whereNotNull('membership_id')->whereNotNull('session_booking_id'), 'paid_at')->count(),
            'receivables' => ['basis' => 'current_snapshot', 'as_of' => Carbon::today('Asia/Manila')->toDateString(),
                'historical_available' => false, ...(new Receivables)->summaryForGym($gym)]];
    }

    public function stock(Gym $gym, Period $period): array
    {
        $items = StockItem::where('gym_id', $gym->id)->active()->orderBy('name')->orderBy('id')->get()
            ->map(fn (StockItem $item) => ['id' => $item->id, 'name' => $item->name, 'category' => $item->category->value,
                'unit' => $item->unit, 'location' => $item->location, 'quantity' => $item->quantity,
                'min_level' => $item->min_level, 'low' => $item->isLow()]);
        $purchasing = ['basis' => 'created_in_range_current_status'];
        foreach (['requests' => 'purchase_requests', 'orders' => 'purchase_orders'] as $key => $table) {
            $purchasing[$key] = $period->apply(DB::table($table)->where('gym_id', $gym->id), 'created_at')
                ->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->orderBy('status')->get()
                ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->count])->all();
        }
        $receipts = $period->apply(DB::table('goods_receipts')->where('gym_id', $gym->id), 'received_on', false)
            ->selectRaw('COUNT(*) as count, SUM(total) as total')->first();
        $purchasing['receipts'] = ['count' => (int) $receipts->count, 'total' => Money::toDecimal(Money::toCents($receipts->total))];

        return ['as_of' => Carbon::today('Asia/Manila')->toDateString(),
            'summary' => ['items' => $items->count(), 'low_items' => $items->where('low', true)->count(), 'out_items' => $items->where('quantity', 0)->count()],
            'items' => $items->all(), 'purchasing' => $purchasing];
    }

    private function staffRates(array $counts): array
    {
        return [...$counts, 'attendance_rate' => Metrics::rate($counts['present'] + $counts['late'], $counts['present'] + $counts['late'] + $counts['absent']),
            'late_rate' => Metrics::rate($counts['late'], $counts['present'] + $counts['late'])];
    }

    public function monthlyFinance(Gym $gym, Period $period, string $basis = 'income_expense'): array
    {
        $reports = new Reports;
        $report = match ($basis) {
            'income_expense' => $reports->incomeAgainstExpenses($gym, $period->from(), $period->to()),
            'cash_flow' => $reports->cashFlow($gym, $period->from(), $period->to()),
            default => throw new \InvalidArgumentException('Unknown forecast basis.'),
        };
        $fields = $basis === 'cash_flow' ? ['in', 'out', 'net'] : ['income', 'expenses', 'net'];
        $months = [];
        foreach ($period->monthKeys() as $month) $months[$month] = array_fill_keys($fields, 0);
        foreach ($report['buckets'] as $bucket) {
            $month = substr($bucket['period'], 0, 7);
            foreach ($fields as $field) $months[$month][$field] += Money::toCents($bucket[$field]);
        }
        $buckets = [];
        foreach ($months as $month => $amounts) $buckets[] = ['period' => $month, ...array_map(fn ($cents) => Money::toDecimal($cents), $amounts)];

        return ['buckets' => $buckets, 'totals' => $report['totals']];
    }
}
