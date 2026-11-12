<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

/**
 * R-112: optional from/to date filter (gym dates, Asia/Manila) for history lists.
 * Both ends are inclusive whole days. No dates given = no filter.
 */
final class DateRange
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when(fn (Fluent $input) => filled($input->from), 'after_or_equal:from')],
        ];
    }

    /** Rows whose date column falls inside the range. */
    public static function apply(Builder $query, array $data, string $column): Builder
    {
        return $query
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate($column, '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate($column, '<=', $to));
    }

    /** Rows whose start..end period touches the range (leave, payroll periods). */
    public static function overlap(Builder $query, array $data, string $start, string $end): Builder
    {
        return $query
            ->when($data['from'] ?? null, fn ($q, $from) => $q->whereDate($end, '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->whereDate($start, '<=', $to));
    }
}
