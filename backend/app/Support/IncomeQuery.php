<?php

namespace App\Support;

use App\Models\Gym;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * R-63: one query layer for recorded income. The payments table stays the only
 * money-in ledger, so this only reads and joins the context the screen needs.
 */
class IncomeQuery
{
    /** A page-ready query limited to one gym, newest first. */
    public function forGym(Gym $gym, string|\DateTimeInterface|null $from = null, string|\DateTimeInterface|null $to = null, ?string $type = null): Builder
    {
        $from = $this->date($from);
        $to = $this->date($to);

        $query = $this->base($gym)
            ->select([
                'payments.id',
                'payments.income_type as type',
                'payments.amount',
                'payments.discount',
                'payments.method',
                'payments.paid_at',
                'payments.payer_name',
                'payments.receipt_number',
                'payments.note',
                'payments.corrects_id',
                'payments.membership_id',
                'payments.session_booking_id as booking_id',
                'users.first_name as member_first_name',
                'users.last_name as member_last_name',
            ])
            ->orderByDesc('payments.paid_at')
            ->orderByDesc('payments.id');

        $this->filter($query, $from, $to, $type);

        return $query;
    }

    /** Totals for the same filters, grouped by income type. */
    public function totals(Gym $gym, string|\DateTimeInterface|null $from = null, string|\DateTimeInterface|null $to = null, ?string $type = null): Collection
    {
        $query = DB::table('payments')
            ->where('payments.gym_id', $gym->id)
            ->selectRaw('payments.income_type as type, SUM(payments.amount) as amount')
            ->groupBy('payments.income_type')
            ->orderBy('payments.income_type');

        $this->filter($query, $this->date($from), $this->date($to), $type);

        return $query->get()->map(fn ($row) => [
            'type' => $row->type,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
        ])->values();
    }

    private function base(Gym $gym): Builder
    {
        return DB::table('payments')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('session_bookings', 'session_bookings.id', '=', 'payments.session_booking_id')
            ->leftJoin('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
            ->where('payments.gym_id', $gym->id);
    }

    private function date(string|\DateTimeInterface|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private function filter(Builder $query, ?string $from, ?string $to, ?string $type): void
    {
        $type = $type === 'all' ? null : $type;

        $query
            ->when($from, fn (Builder $q) => $q->whereDate('payments.paid_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('payments.paid_at', '<=', $to))
            ->when($type, fn (Builder $q) => $q->where('payments.income_type', $type));
    }
}
