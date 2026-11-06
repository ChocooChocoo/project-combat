<?php

namespace App\Support\Finance;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * R-65: what members still owe. Derived with the same formulas the ledgers use,
 * never stored. Membership balances and session/private-coaching fees merge per
 * member, and the sources stay for drill-down.
 */
class Receivables
{
    public function forGym(Gym $gym): Collection
    {
        return $this->grouped($gym->id, null);
    }

    /** @return array{balance: string, sources: list<array{kind: string, id: int, label: string, amount: string}>} */
    public function forUser(User $user): array
    {
        $row = $this->grouped(null, $user->id)->firstWhere('user_id', $user->id);

        return [
            'balance' => $row['total'] ?? '0.00',
            'sources' => $row['sources'] ?? [],
        ];
    }

    private function grouped(?int $gymId, ?int $userId): Collection
    {
        $grouped = [];

        foreach ($this->membershipRows($gymId, $userId) as $row) {
            $amount = round((float) $row->balance, 2);
            if ($amount <= 0) {
                continue;
            }

            $this->add($grouped, $row, [
                'kind' => 'membership',
                'id' => (int) $row->id,
                'label' => $row->plan_name ?: "Membership #{$row->id}",
                'amount' => $this->money($amount),
            ]);
        }

        foreach ($this->bookingRows($gymId, $userId) as $row) {
            $amount = round((float) $row->balance, 2);
            if ($amount <= 0) {
                continue;
            }

            $private = $row->session_type === 'private_coaching';
            $session = $row->session_name ?: 'Session';
            $when = $row->starts_at ? Carbon::parse($row->starts_at)->format('Y-m-d') : null;

            $this->add($grouped, $row, [
                'kind' => $private ? 'private_coaching' : 'booking',
                'id' => (int) $row->id,
                'label' => ($private ? 'Private coaching: ' : 'Session: ').$session.($when ? " — {$when}" : ''),
                'amount' => $this->money($amount),
            ]);
        }

        return collect($grouped)
            ->map(fn (array $row) => [
                'user_id' => $row['user_id'],
                'member_name' => $row['member_name'],
                'total' => $this->money($row['total_cents'] / 100),
                'sources' => $row['sources'],
            ])
            ->sortBy('member_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @param array<int, array{user_id: int, member_name: string, total_cents: int, sources: list<array<string, mixed>>}> $grouped */
    private function add(array &$grouped, object $row, array $source): void
    {
        $id = (int) $row->user_id;
        $grouped[$id] ??= [
            'user_id' => $id,
            'member_name' => trim("{$row->first_name} {$row->last_name}"),
            'total_cents' => 0,
            'sources' => [],
        ];
        $grouped[$id]['total_cents'] += (int) round((float) $source['amount'] * 100);
        $grouped[$id]['sources'][] = $source;
    }

    private function membershipRows(?int $gymId, ?int $userId): Collection
    {
        $paid = DB::table('payments')
            ->select('membership_id')
            ->selectRaw('SUM(amount) as paid')
            ->whereNotNull('membership_id')
            ->groupBy('membership_id');

        return DB::table('memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->leftJoin('membership_plans', 'membership_plans.id', '=', 'memberships.membership_plan_id')
            ->leftJoinSub($paid, 'mp', 'mp.membership_id', '=', 'memberships.id')
            ->when($gymId, fn ($query) => $query->where('memberships.gym_id', $gymId))
            ->when($userId, fn ($query) => $query->where('memberships.user_id', $userId))
            ->whereRaw('memberships.amount_charged - COALESCE(mp.paid, 0) > 0')
            ->orderBy('memberships.user_id')
            ->orderBy('memberships.id')
            ->get([
                'memberships.id',
                'memberships.user_id',
                'users.first_name',
                'users.last_name',
                'membership_plans.name as plan_name',
                DB::raw('memberships.amount_charged - COALESCE(mp.paid, 0) as balance'),
            ]);
    }

    private function bookingRows(?int $gymId, ?int $userId): Collection
    {
        $paid = DB::table('payments')
            ->select('session_booking_id')
            ->selectRaw('SUM(amount) as paid')
            ->whereNotNull('session_booking_id')
            ->groupBy('session_booking_id');

        return DB::table('session_bookings')
            ->join('users', 'users.id', '=', 'session_bookings.user_id')
            ->join('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
            ->leftJoinSub($paid, 'bp', 'bp.session_booking_id', '=', 'session_bookings.id')
            ->when($gymId, fn ($query) => $query->where('session_bookings.gym_id', $gymId))
            ->when($userId, fn ($query) => $query->where('session_bookings.user_id', $userId))
            ->whereIn('session_bookings.status', ['requested', 'booked'])
            ->where('session_bookings.fee', '>', 0)
            ->whereRaw('session_bookings.fee - COALESCE(bp.paid, 0) > 0')
            ->orderBy('session_bookings.user_id')
            ->orderBy('session_bookings.id')
            ->get([
                'session_bookings.id',
                'session_bookings.user_id',
                'users.first_name',
                'users.last_name',
                'training_sessions.name as session_name',
                'training_sessions.type as session_type',
                'training_sessions.starts_at',
                DB::raw('session_bookings.fee - COALESCE(bp.paid, 0) as balance'),
            ]);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
