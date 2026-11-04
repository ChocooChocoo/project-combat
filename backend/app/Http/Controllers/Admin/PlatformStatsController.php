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
        ]);
    }
}
