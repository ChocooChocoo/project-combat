<?php

namespace App\Support\Finance;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * R-67: budget against actual. The rows are read and written for one month,
 * with the actual sides taken straight from the payment and expense ledgers.
 */
class BudgetActual
{
    private const KINDS = ['income', 'expense'];

    /** @return array{month: string, categories: array{income: list<string>, expense: list<string>}, budgets: list<array<string, string|null>>} */
    public function forMonth(Gym $gym, Carbon|string $month): array
    {
        $start = $this->monthStart($month);

        $incomeActual = $this->sumBy(
            DB::table('payments')
                ->where('gym_id', $gym->id)
                ->where('paid_at', '>=', $start->toDateTimeString())
                ->where('paid_at', '<', $start->copy()->addMonthNoOverflow()->toDateTimeString()),
            'income_type',
            'amount',
        );

        $expenseActual = $this->sumBy(
            DB::table('expenses')
                ->where('gym_id', $gym->id)
                ->where('incurred_on', '>=', $start->toDateString())
                ->where('incurred_on', '<', $start->copy()->addMonthNoOverflow()->toDateString()),
            'category',
            'amount',
        );

        $stored = DB::table('budgets')
            ->where('gym_id', $gym->id)
            ->where('month', '>=', $start->toDateString())
            ->where('month', '<', $start->copy()->addMonthNoOverflow()->toDateString())
            ->get(['kind', 'category', 'amount'])
            ->keyBy(fn (object $row) => "{$row->kind}|{$row->category}");

        $categories = [
            'income' => array_keys(config('finance.income_types', [])),
            'expense' => array_keys(config('finance.expense_categories', [])),
        ];

        $actuals = ['income' => $incomeActual, 'expense' => $expenseActual];
        $rows = [];

        foreach (self::KINDS as $kind) {
            foreach ($categories[$kind] as $category) {
                $budgetCents = isset($stored["{$kind}|{$category}"])
                    ? $this->cents($stored["{$kind}|{$category}"]->amount)
                    : 0;
                $actualCents = $actuals[$kind][$category] ?? 0;
                $differenceCents = $budgetCents - $actualCents;

                $rows[] = [
                    'kind' => $kind,
                    'category' => $category,
                    'budget' => $this->money($budgetCents),
                    'actual' => $this->money($actualCents),
                    'difference' => $this->money($differenceCents),
                    'percentage' => $budgetCents > 0
                        ? number_format($actualCents / $budgetCents * 100, 2, '.', '')
                        : null,
                ];
            }
        }

        return [
            'month' => $start->format('Y-m'),
            'categories' => $categories,
            'budgets' => $rows,
        ];
    }

    /**
     * Upserts the submitted category amounts for one month, then returns the
     * whole month so the screen shows budget, actual and difference together.
     *
     * @param  list<array{kind: string, category: string, amount: numeric-string|int|float}>  $budgets
     * @return array{month: string, categories: array{income: list<string>, expense: list<string>}, budgets: list<array<string, string|null>>}
     */
    public function upsert(Gym $gym, Carbon|string $month, array $budgets, User $actor): array
    {
        $start = $this->monthStart($month);
        $now = now();
        $rows = [];
        $seen = [];

        foreach (array_values($budgets) as $index => $row) {
            $kind = is_array($row) ? ($row['kind'] ?? null) : null;
            $category = is_array($row) ? ($row['category'] ?? null) : null;
            $amount = is_array($row) ? ($row['amount'] ?? null) : null;

            if (! in_array($kind, self::KINDS, true)) {
                throw ValidationException::withMessages([
                    "budgets.{$index}.kind" => 'Choose income or expense.',
                ]);
            }

            $allowed = $kind === 'income'
                ? config('finance.income_types', [])
                : config('finance.expense_categories', []);

            if (! is_string($category) || ! array_key_exists($category, $allowed)) {
                throw ValidationException::withMessages([
                    "budgets.{$index}.category" => 'Choose a category that belongs to this budget kind.',
                ]);
            }

            $key = "{$kind}|{$category}";

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "budgets.{$index}.category" => 'Each category can appear only once for a month.',
                ]);
            }

            $seen[$key] = true;

            if (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > 9999999999.99) {
                throw ValidationException::withMessages([
                    "budgets.{$index}.amount" => 'Enter a budget amount of zero or more.',
                ]);
            }

            $rows[] = [
                'gym_id' => $gym->id,
                'kind' => $kind,
                'category' => $category,
                'month' => $start->toDateString(),
                'amount' => number_format((float) $amount, 2, '.', ''),
                'updated_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('budgets')->upsert(
                $rows,
                ['gym_id', 'kind', 'category', 'month'],
                ['amount', 'updated_by', 'updated_at'],
            );
        }

        return $this->forMonth($gym, $start);
    }

    /** @return array<string, int> */
    private function sumBy(Builder $query, string $group, string $sum): array
    {
        $totals = [];

        foreach ($query->selectRaw("{$group}, SUM({$sum}) as total")->groupBy($group)->get() as $row) {
            $totals[(string) $row->{$group}] = $this->cents($row->total);
        }

        return $totals;
    }

    private function monthStart(Carbon|string $month): Carbon
    {
        if (! $month instanceof Carbon) {
            $month = preg_match('/^\d{4}-\d{2}$/', $month) === 1
                ? Carbon::createFromFormat('!Y-m', $month)
                : Carbon::parse($month);
        }

        return $month->copy()->startOfMonth()->startOfDay();
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
