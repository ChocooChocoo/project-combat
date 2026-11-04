<?php

namespace App\Http\Controllers;

use App\Enums\GymStatus;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;

/** R-18: approved gyms are visible on the platform with their tier badge. Search comes with R-30. */
class PublicGymController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'gyms' => Gym::where('status', GymStatus::Approved)
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'province', 'disciplines', 'approved_tier', 'latitude', 'longitude'])
                ->map(fn (Gym $gym) => [
                    ...$gym->only(['id', 'name', 'city', 'province', 'disciplines', 'latitude', 'longitude']),
                    'tier' => $gym->approved_tier,
                    'badge' => config("verification.tiers.{$gym->approved_tier}.badge"),
                ]),
        ]);
    }
}
