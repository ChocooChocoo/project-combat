<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Models\GymVisit;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\SessionBooking;
use App\Support\Finance\Receivables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P07: the signed-in person's own training numbers across every gym they belong to.
 * Reads only rows where user_id is this person, so the gym scope is left off on purpose.
 */
class MemberDashboardController extends Controller
{
    public const VISIT_WEEKS = 26;

    public const MONTHS = 6;

    public function __invoke(Request $request, Receivables $receivables): JsonResponse
    {
        $user = $request->user();
        $since = now()->startOfWeek()->subWeeks(self::VISIT_WEEKS - 1);
        $monthsFrom = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $months = collect(range(0, self::MONTHS - 1))->map(fn ($i) => $monthsFrom->copy()->addMonths($i)->format('Y-m'));

        // ponytail: small per-person row counts, so grouping happens in PHP instead of per-driver SQL.
        $visits = GymVisit::withoutGlobalScope('gym')->where('user_id', $user->id)
            ->where('checked_in_at', '>=', $since)->pluck('checked_in_at')
            ->countBy(fn ($at) => $at->format('Y-m-d'));

        $bookings = SessionBooking::withoutGlobalScope('gym')->where('session_bookings.user_id', $user->id)
            ->join('training_sessions', 'training_sessions.id', '=', 'session_bookings.training_session_id')
            ->get(['session_bookings.status', 'session_bookings.attendance', 'training_sessions.starts_at']);
        $attended = $bookings->filter(fn ($b) => in_array($b->attendance, ['present', 'late'], true));
        $attendedByMonth = $attended->countBy(fn ($b) => substr((string) $b->starts_at, 0, 7));

        $paid = Payment::withoutGlobalScope('gym')->where('user_id', $user->id)
            ->where('paid_at', '>=', $monthsFrom)->get(['amount', 'paid_at'])
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))
            ->map(fn ($rows) => $rows->sum(fn ($p) => (float) $p->amount));

        $memberships = Membership::withoutGlobalScope('gym')->where('user_id', $user->id)
            ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Frozen->value])
            ->with(['gym:id,name', 'plan' => fn ($q) => $q->withoutGlobalScope('gym')])
            ->orderBy('ends_on')->get();

        return response()->json([
            'visits' => $visits->map(fn ($count, $date) => ['date' => $date, 'count' => $count])->values(),
            'visits_since' => $since->toDateString(),
            'sessions' => [
                'attended' => $attended->count(),
                'late' => $bookings->filter(fn ($b) => $b->attendance === 'late')->count(),
                'no_show' => $bookings->filter(fn ($b) => in_array($b->attendance, ['absent', 'no_show'], true))->count(),
                'upcoming' => $bookings->filter(fn ($b) => $b->status->isLive() && now()->lt($b->starts_at))->count(),
            ],
            'monthly' => $months->map(fn ($m) => [
                'period' => $m,
                'attended' => $attendedByMonth[$m] ?? 0,
                'paid' => number_format($paid[$m] ?? 0, 2, '.', ''),
            ]),
            'memberships' => $memberships->map(fn (Membership $m) => [
                'id' => $m->id,
                'gym' => $m->gym?->name,
                'plan' => $m->plan?->name,
                'status' => $m->status->value,
                'starts_on' => $m->starts_on?->toDateString(),
                'ends_on' => $m->ends_on?->toDateString(),
                'days_left' => $m->ends_on ? max(0, (int) now()->startOfDay()->diffInDays($m->ends_on, false)) : null,
            ]),
            'balance' => $receivables->forUser($user)['balance'],
        ]);
    }
}
