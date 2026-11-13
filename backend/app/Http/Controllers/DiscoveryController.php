<?php

namespace App\Http\Controllers;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\GymPreference;
use App\Models\MembershipPlan;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-30, R-97 and R-98: search, filtering and recommendations. */
class DiscoveryController extends Controller
{
    public function recommendations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $preferences = $this->preferencePayload($request->user()->gymPreference()->first());
        $weights = config('discovery.weights');
        $cap = (float) config('discovery.distance_cap_km');

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        $hasLocation = $lat !== null && $lng !== null;

        $discipline = $preferences['discipline'];
        $minPrice = $preferences['min_price'] !== null ? (float) $preferences['min_price'] : null;
        $maxPrice = $preferences['max_price'] !== null ? (float) $preferences['max_price'] : null;
        $hasPrice = $minPrice !== null || $maxPrice !== null;

        $available = 0;

        if ($discipline !== null) {
            $available += $weights['discipline'];
        }

        if ($hasPrice) {
            $available += $weights['price'];
        }

        if ($hasLocation) {
            $available += $weights['distance'];
        }

        $gyms = Gym::where('status', GymStatus::Approved)->orderBy('name')->get();

        $minFees = MembershipPlan::withoutGlobalScope('gym')
            ->whereIn('gym_id', $gyms->pluck('id'))
            ->whereNotNull('published_at')
            ->selectRaw('gym_id, MIN(total_fee) as min_fee')
            ->groupBy('gym_id')
            ->pluck('min_fee', 'gym_id');

        $rows = $gyms->map(function (Gym $gym) use (
            $discipline, $minPrice, $maxPrice, $hasPrice, $hasLocation, $lat, $lng, $weights, $cap, $minFees, $available,
        ) {
            $score = 0.0;
            $reasons = [];
            $distance = null;

            if ($hasLocation && $gym->latitude !== null && $gym->longitude !== null) {
                $distance = round(Geo::distanceKm($lat, $lng, $gym->latitude, $gym->longitude), 1);
                $factor = $cap > 0 ? max(0, 1 - $distance / $cap) : 0;

                if ($factor > 0) {
                    $score += $weights['distance'] * $factor;
                    $reasons[] = number_format($distance, 1, '.', '').' km away';
                }
            }

            if ($discipline !== null && in_array($discipline, $gym->disciplines ?? [], true)) {
                $score += $weights['discipline'];
                $reasons[] = 'Teaches '.config("verification.disciplines.{$discipline}", $discipline);
            }

            $fee = $minFees[$gym->id] ?? null;
            $minPriceValue = $fee === null ? null : number_format((float) $fee, 2, '.', '');

            if ($hasPrice && $fee !== null) {
                $fee = (float) $fee;

                if (($minPrice === null || $fee >= $minPrice) && ($maxPrice === null || $fee <= $maxPrice)) {
                    $score += $weights['price'];
                    $reasons[] = 'Plan from ₱'.number_format($fee);
                }
            }

            return [
                'gym' => [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'city' => $gym->city,
                    'province' => $gym->province,
                    'disciplines' => $gym->disciplines ?? [],
                    'tier' => $gym->approved_tier,
                    'badge' => config("verification.tiers.{$gym->approved_tier}.badge"),
                    'latitude' => $gym->latitude,
                    'longitude' => $gym->longitude,
                    'distance_km' => $distance,
                    'min_price' => $minPriceValue,
                ],
                'score' => $available > 0 ? round($score / $available * 100, 2) : 0,
                'reasons' => $reasons ?: ['Approved gym'],
            ];
        });

        $rows = $rows->sort(function (array $left, array $right) {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            $leftDistance = $left['gym']['distance_km'];
            $rightDistance = $right['gym']['distance_km'];

            if ($leftDistance === null && $rightDistance === null) {
                return strcasecmp($left['gym']['name'], $right['gym']['name']);
            }

            if ($leftDistance === null) {
                return 1;
            }

            if ($rightDistance === null) {
                return -1;
            }

            return $leftDistance <=> $rightDistance
                ?: strcasecmp($left['gym']['name'], $right['gym']['name']);
        });

        return response()->json([
            'recommendations' => $rows->take(config('discovery.limit'))->values(),
            'preferences' => $preferences,
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'options' => [
                'disciplines' => config('verification.disciplines'),
                'tiers' => config('verification.tiers'),
                'weekdays' => config('hr.weekdays'),
                'preferred_times' => config('discovery.preferred_times'),
                'activity_types' => config('offer.activity_types'),
            ],
        ]);
    }

    private function preferencePayload(?GymPreference $preference): array
    {
        return [
            'discipline' => $preference?->discipline,
            'min_price' => $preference?->min_price,
            'max_price' => $preference?->max_price,
            'preferred_times' => $preference?->preferred_times ?? [],
        ];
    }
}
