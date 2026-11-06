<?php

namespace App\Support\Finance;

use App\Enums\PayrollStatus;
use App\Models\Gym;
use App\Support\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R-68: sustainability reports. Every query filters gym_id explicitly rather
 * than leaning on the model scope, because the aggregates use raw SQL.
 *
 * A range of 62 days or less is bucketed by day; a longer range by month.
 */
class Reports
{
    public const DAILY_BUCKET_LIMIT_DAYS = 62;

    /** @return array{buckets: list<array<string, string>>, totals: array<string, string>} */
    public function cashFlow(Gym $gym, Carbon|string $from, Carbon|string $to): array
    {
        [$start, $end, $monthly, $periods] = $this->window($from, $to);

        $in = $this->moneyByPeriod(
            DB::table('payments')->where('gym_id', $gym->id),
            'paid_at',
            'amount',
            $monthly,
            $start,
            $end,
            timestamp: true,
        );

        $expenses = $this->moneyByPeriod(
            DB::table('expenses')
                ->where('gym_id', $gym->id)
                ->where(fn (Builder $query) => $query
                    ->whereNull('source_type')
                    ->orWhere('source_type', 'payroll_run')),
            'incurred_on',
            'amount',
            $monthly,
            $start,
            $end,
        );

        $supplierPayments = $this->moneyByPeriod(
            DB::table('supplier_payments')->where('gym_id', $gym->id),
            'paid_on',
            'amount',
            $monthly,
            $start,
            $end,
        );

        $buckets = [];
        $totalIn = 0;
        $totalOut = 0;

        foreach ($periods as $period) {
            $inCents = $in[$period] ?? 0;
            $outCents = ($expenses[$period] ?? 0) + ($supplierPayments[$period] ?? 0);

            $totalIn += $inCents;
            $totalOut += $outCents;

            $buckets[] = [
                'period' => $period,
                'in' => $this->money($inCents),
                'out' => $this->money($outCents),
                'net' => $this->money($inCents - $outCents),
            ];
        }

        return [
            'buckets' => $buckets,
            'totals' => [
                'in' => $this->money($totalIn),
                'out' => $this->money($totalOut),
                'net' => $this->money($totalIn - $totalOut),
            ],
        ];
    }

    /** @return array{buckets: list<array<string, string>>, totals: array<string, string>} */
    public function incomeAgainstExpenses(Gym $gym, Carbon|string $from, Carbon|string $to): array
    {
        [$start, $end, $monthly, $periods] = $this->window($from, $to);

        $income = $this->moneyByPeriod(
            DB::table('payments')->where('gym_id', $gym->id),
            'paid_at',
            'amount',
            $monthly,
            $start,
            $end,
            timestamp: true,
        );

        // Accrual view: received purchases count as an expense here even though
        // the cash-flow report waits for the supplier payment.
        $expenses = $this->moneyByPeriod(
            DB::table('expenses')->where('gym_id', $gym->id),
            'incurred_on',
            'amount',
            $monthly,
            $start,
            $end,
        );

        $buckets = [];
        $totalIncome = 0;
        $totalExpenses = 0;

        foreach ($periods as $period) {
            $incomeCents = $income[$period] ?? 0;
            $expenseCents = $expenses[$period] ?? 0;

            $totalIncome += $incomeCents;
            $totalExpenses += $expenseCents;

            $buckets[] = [
                'period' => $period,
                'income' => $this->money($incomeCents),
                'expenses' => $this->money($expenseCents),
                'net' => $this->money($incomeCents - $expenseCents),
            ];
        }

        return [
            'buckets' => $buckets,
            'totals' => [
                'income' => $this->money($totalIncome),
                'expenses' => $this->money($totalExpenses),
                'net' => $this->money($totalIncome - $totalExpenses),
            ],
        ];
    }

    /** @return array{buckets: list<array<string, string|int>>, totals: array<string, string|int>} */
    public function payrollSummary(Gym $gym, Carbon|string $from, Carbon|string $to): array
    {
        [$start, $end, $monthly, $periods] = $this->window($from, $to);

        $expression = $this->bucket('payroll_runs.ends_on', $monthly);
        $query = $this->releasedLines($gym)
            ->selectRaw("{$expression} as period")
            ->selectRaw('SUM(payroll_lines.gross_pay) as gross, SUM(payroll_lines.net_pay) as net')
            ->selectRaw('SUM(payroll_lines.sss_employer + payroll_lines.ec_employer + payroll_lines.philhealth_employer + payroll_lines.pagibig_employer) as employer_statutory')
            ->selectRaw('COUNT(payroll_lines.id) as line_count')
            ->groupByRaw($expression);

        $this->applyRange($query, 'payroll_runs.ends_on', $start, $end);

        $rows = [];

        foreach ($query->get() as $row) {
            $rows[(string) $row->period] = $row;
        }

        $buckets = [];
        $totalGross = 0;
        $totalNet = 0;
        $totalEmployer = 0;
        $totalCount = 0;

        foreach ($periods as $period) {
            $row = $rows[$period] ?? null;
            $grossCents = $row ? $this->cents($row->gross) : 0;
            $netCents = $row ? $this->cents($row->net) : 0;
            $employerCents = $row ? $this->cents($row->employer_statutory) : 0;
            $count = $row ? (int) $row->line_count : 0;

            $totalGross += $grossCents;
            $totalNet += $netCents;
            $totalEmployer += $employerCents;
            $totalCount += $count;

            $buckets[] = [
                'period' => $period,
                'gross' => $this->money($grossCents),
                'net' => $this->money($netCents),
                'employer_statutory' => $this->money($employerCents),
                'count' => $count,
            ];
        }

        return [
            'buckets' => $buckets,
            'totals' => [
                'gross' => $this->money($totalGross),
                'net' => $this->money($totalNet),
                'employer_statutory' => $this->money($totalEmployer),
                'count' => $totalCount,
            ],
        ];
    }

    /** @return array{buckets: list<array<string, string>>, totals: array<string, string>} */
    public function payouts(Gym $gym, Carbon|string $from, Carbon|string $to): array
    {
        [$start, $end, $monthly, $periods] = $this->window($from, $to);

        $expression = $this->bucket('payroll_runs.released_at', $monthly);
        $query = $this->releasedLines($gym)
            ->selectRaw("{$expression} as period")
            ->selectRaw('SUM(payroll_lines.net_pay) as payroll_net')
            ->groupByRaw($expression);

        $this->applyRange($query, 'payroll_runs.released_at', $start, $end, timestamp: true);

        $payroll = [];

        foreach ($query->get() as $row) {
            $payroll[(string) $row->period] = $this->cents($row->payroll_net);
        }

        $supplier = $this->moneyByPeriod(
            DB::table('supplier_payments')->where('gym_id', $gym->id),
            'paid_on',
            'amount',
            $monthly,
            $start,
            $end,
        );

        $buckets = [];
        $totalPayroll = 0;
        $totalSupplier = 0;

        foreach ($periods as $period) {
            $payrollCents = $payroll[$period] ?? 0;
            $supplierCents = $supplier[$period] ?? 0;

            $totalPayroll += $payrollCents;
            $totalSupplier += $supplierCents;

            $buckets[] = [
                'period' => $period,
                'payroll_net' => $this->money($payrollCents),
                'supplier_payments' => $this->money($supplierCents),
                'total' => $this->money($payrollCents + $supplierCents),
            ];
        }

        return [
            'buckets' => $buckets,
            'totals' => [
                'payroll_net' => $this->money($totalPayroll),
                'supplier_payments' => $this->money($totalSupplier),
                'total' => $this->money($totalPayroll + $totalSupplier),
            ],
        ];
    }

    /** Released runs with their lines, so the totals cannot drift from the lines. */
    private function releasedLines(Gym $gym): Builder
    {
        return DB::table('payroll_runs')
            ->join('payroll_lines', 'payroll_lines.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.gym_id', $gym->id)
            ->where('payroll_lines.gym_id', $gym->id)
            ->where('payroll_runs.status', PayrollStatus::Released->value);
    }

    /** @return array<string, int> period key => cents */
    private function moneyByPeriod(
        Builder $query,
        string $column,
        string $sum,
        bool $monthly,
        Carbon $start,
        Carbon $end,
        bool $timestamp = false,
    ): array {
        $expression = $this->bucket($column, $monthly);

        $this->applyRange($query, $column, $start, $end, $timestamp);

        $totals = [];

        foreach ($query
            ->selectRaw("{$expression} as period, SUM({$sum}) as total")
            ->groupByRaw($expression)
            ->get() as $row) {
            $totals[(string) $row->period] = $this->cents($row->total);
        }

        return $totals;
    }

    private function bucket(string $column, bool $monthly): string
    {
        return $monthly ? Sql::month($column) : "DATE({$column})";
    }

    private function applyRange(Builder $query, string $column, Carbon $start, Carbon $end, bool $timestamp = false): void
    {
        if ($timestamp) {
            $query->where($column, '>=', $start->toDateTimeString())
                ->where($column, '<', $end->copy()->addDay()->toDateTimeString());

            return;
        }

        $query->where($column, '>=', $start->toDateString())
            ->where($column, '<', $end->copy()->addDay()->toDateString());
    }

    /** @return array{0: Carbon, 1: Carbon, 2: bool, 3: list<string>} */
    private function window(Carbon|string $from, Carbon|string $to): array
    {
        $start = ($from instanceof Carbon ? $from->copy() : Carbon::parse($from))->startOfDay();
        $end = ($to instanceof Carbon ? $to->copy() : Carbon::parse($to))->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        $days = (int) $start->diffInDays($end) + 1;
        $monthly = $days > self::DAILY_BUCKET_LIMIT_DAYS;

        return [$start, $end, $monthly, $this->periods($start, $end, $monthly)];
    }

    /** @return list<string> */
    private function periods(Carbon $start, Carbon $end, bool $monthly): array
    {
        $periods = [];
        $cursor = $monthly ? $start->copy()->startOfMonth() : $start->copy();
        $last = $monthly ? $end->copy()->startOfMonth() : $end->copy();

        while ($cursor->lessThanOrEqualTo($last)) {
            $periods[] = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return $periods;
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
