<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GymStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;

/**
 * R-19: platform-wide numbers only. Counts the gyms table and nothing else,
 * so no gym's members, money or staff records are read.
 */
class PlatformStatsController extends Controller
{
    public const MONTHS = 12;

    public function __invoke(): JsonResponse
    {
        $byStatus = Gym::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byTier = Gym::toBase()->where('status', GymStatus::Approved->value)
            ->selectRaw('approved_tier, count(*) as total')->groupBy('approved_tier')->pluck('total', 'approved_tier');

        $count = fn (GymStatus ...$statuses) => collect($statuses)->sum(fn ($s) => (int) ($byStatus[$s->value] ?? 0));

        return response()->json([
            'gyms_registered' => $count(GymStatus::Submitted, GymStatus::UnderReview, GymStatus::Returned, GymStatus::Approved, GymStatus::Rejected),
            'gyms_verified' => $count(GymStatus::Approved),
            'verified_by_tier' => collect(config('verification.tiers'))->keys()
                ->mapWithKeys(fn ($tier) => [$tier => (int) ($byTier[$tier] ?? 0)]),
            'applications_pending' => $count(GymStatus::Submitted, GymStatus::UnderReview),
            'applications_returned' => $count(GymStatus::Returned),
            'applications_rejected' => $count(GymStatus::Rejected),
            // P07: dashboard trend and plan mix, still from the gyms table only.
            'monthly' => $this->monthly(),
            'subscriptions' => $this->subscriptions(),
        ]);
    }

    /** Applications sent and gyms approved per month, last twelve months. */
    private function monthly(): array
    {
        $from = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $sent = Gym::toBase()->where('submitted_at', '>=', $from)->pluck('submitted_at')
            ->countBy(fn ($at) => substr((string) $at, 0, 7));
        $approved = Gym::toBase()->where('status', GymStatus::Approved->value)->where('reviewed_at', '>=', $from)
            ->pluck('reviewed_at')->countBy(fn ($at) => substr((string) $at, 0, 7));

        return collect(range(0, self::MONTHS - 1))->map(function ($i) use ($from, $sent, $approved) {
            $month = $from->copy()->addMonths($i)->format('Y-m');

            return ['period' => $month, 'submitted' => $sent[$month] ?? 0, 'approved' => $approved[$month] ?? 0];
        })->all();
    }

    /** Live gyms by plan state, using the same order as Subscription::state (trial, paid, free). */
    private function subscriptions(): array
    {
        $now = now();
        $counts = ['trial' => 0, 'paid' => 0, 'free' => 0, 'unmanaged' => 0];
        Gym::toBase()->where('status', GymStatus::Approved->value)->get(['trial_ends_at', 'paid_until'])
            ->each(function ($gym) use ($now, &$counts) {
                $state = match (true) {
                    $gym->trial_ends_at === null => 'unmanaged',
                    $now->lt($gym->trial_ends_at) => 'trial',
                    $gym->paid_until !== null && $now->lt($gym->paid_until) => 'paid',
                    default => 'free',
                };
                $counts[$state]++;
            });

        return $counts;
    }
}
