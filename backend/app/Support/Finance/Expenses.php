<?php

namespace App\Support\Finance;

use App\Models\Expense;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * R-64: the one way automatic costs are posted. Payroll (6F) and purchasing (6E)
 * call this so a retried job cannot post the same source twice.
 */
class Expenses
{
    public const SOURCE_TYPES = ['payroll_run', 'goods_receipt'];

    /** Automatic source keys: source type and id are both set, or neither is. */
    public function post(
        Gym $gym,
        string $category,
        string $amount,
        Carbon $date,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?User $actor = null,
    ): Expense {
        if (! array_key_exists($category, config('finance.expense_categories', []))) {
            throw ValidationException::withMessages(['category' => 'Choose an expense category.']);
        }

        if (($sourceType === null) !== ($sourceId === null)) {
            throw ValidationException::withMessages(['source_type' => 'A source needs both its type and its id.']);
        }

        if ($sourceType !== null && ! in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw ValidationException::withMessages(['source_type' => 'Unknown automatic expense source.']);
        }

        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
        }

        if ($sourceType !== null && $this->sourceExists($sourceType, $sourceId, $category)) {
            throw ValidationException::withMessages(['source_type' => 'This source was already posted as an expense.']);
        }

        app(CurrentGym::class)->set($gym);

        try {
            return Expense::create([
                'category' => $category,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'incurred_on' => $date->toDateString(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'recorded_by' => $actor?->id,
            ]);
        } catch (QueryException $error) {
            // The unique key is the final guard when two jobs race each other.
            if ($sourceType !== null && $this->sourceExists($sourceType, $sourceId, $category)) {
                throw ValidationException::withMessages(['source_type' => 'This source was already posted as an expense.']);
            }

            throw $error;
        }
    }

    private function sourceExists(string $type, int $id, string $category): bool
    {
        return Expense::withoutGlobalScope('gym')
            ->where('source_type', $type)
            ->where('source_id', $id)
            ->where('category', $category)
            ->exists();
    }
}
