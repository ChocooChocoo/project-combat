<?php

namespace App\Support\DecisionSupport;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class Period
{
    private Carbon $start;
    private Carbon $end;

    public function __construct(Carbon|string $from, Carbon|string $to)
    {
        $this->start = $this->day($from);
        $this->end = $this->day($to);
        if ($this->end->lt($this->start)) {
            throw new InvalidArgumentException('Range end must not precede range start.');
        }
    }

    public static function fromRequest(Request $request): self
    {
        $dates = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return new self($dates['from'], $dates['to']);
    }

    public function from(): string
    {
        return $this->start->toDateString();
    }

    public function to(): string
    {
        return $this->end->toDateString();
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function endExclusive(): Carbon
    {
        return $this->end->copy()->addDay();
    }

    public function apply(Builder $query, string $column, bool $timestamp = true): Builder
    {
        return $query->where($column, '>=', $timestamp ? $this->start()->format('Y-m-d H:i:s') : $this->from())
            ->where($column, '<', $timestamp ? $this->endExclusive()->format('Y-m-d H:i:s') : $this->endExclusive()->toDateString());
    }

    public function monthKeys(): array
    {
        $keys = [];
        for ($month = $this->start()->startOfMonth(); $month->lte($this->end); $month->addMonth()) {
            $keys[] = $month->format('Y-m');
        }

        return $keys;
    }

    public function trailingMonths(int $months, bool $completed): self
    {
        if ($months < 1) {
            throw new InvalidArgumentException('A month window must contain at least one month.');
        }
        $today = Carbon::today('Asia/Manila');
        $anchor = $this->end->min($today)->copy();
        if ($completed && (! $anchor->isLastOfMonth() || ! $anchor->lt($today))) {
            $anchor->startOfMonth()->subDay();
        }

        return new self($anchor->copy()->startOfMonth()->subMonths($months - 1), $anchor);
    }

    private function day(Carbon|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('Asia/Manila')->startOfDay();
        }
        $date = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Manila');
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Dates must use exact Y-m-d format.');
        }

        return $date;
    }
}
