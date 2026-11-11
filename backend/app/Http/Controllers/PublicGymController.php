<?php

namespace App\Http\Controllers;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\GymServicePhoto;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** R-18, R-30 and R-97: approved gyms, their search, and the public gym page. */
class PublicGymController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'discipline' => ['nullable', 'string', 'max:40'],
            'service' => ['nullable', 'string', 'max:120'],
            'tier' => ['nullable', 'integer', Rule::in([1, 2])],
            'day' => ['nullable', 'string', Rule::in(array_keys(config('hr.weekdays')))],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'distance'])],
        ]);

        $query = Gym::query()->where('status', GymStatus::Approved);

        if (filled($data['q'] ?? null)) {
            $term = '%'.trim($data['q']).'%';

            $query->where(function ($search) use ($term) {
                $search->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('province', 'like', $term);
            });
        }

        if (filled($data['city'] ?? null)) {
            $query->where('city', 'like', '%'.trim($data['city']).'%');
        }

        if (filled($data['province'] ?? null)) {
            $query->where('province', 'like', '%'.trim($data['province']).'%');
        }

        if (filled($data['discipline'] ?? null)) {
            $query->whereJsonContains('disciplines', $data['discipline']);
        }

        if (filled($data['service'] ?? null)) {
            $service = '%'.trim($data['service']).'%';

            $query->whereHas('gymServices', function ($services) use ($service) {
                $services->withoutGlobalScope('gym')
                    ->whereNotNull('published_at')
                    ->where('name', 'like', $service);
            });
        }

        if (isset($data['tier'])) {
            $query->where('approved_tier', (int) $data['tier']);
        }

        if (isset($data['day'])) {
            $query->whereNotNull("operating_hours->{$data['day']}");
        }

        $gyms = $query
            ->with([
                'gymServices' => fn ($services) => $services->withoutGlobalScope('gym')
                    ->whereNotNull('published_at')
                    ->orderBy('name'),
                'membershipPlans' => fn ($plans) => $plans->withoutGlobalScope('gym')
                    ->whereNotNull('published_at'),
            ])
            ->orderBy('name')
            ->get();

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        $rows = $gyms->map(function (Gym $gym) use ($lat, $lng) {
            $distance = $lat !== null && $lng !== null && $gym->latitude !== null && $gym->longitude !== null
                ? round($this->distanceKm($lat, $lng, $gym->latitude, $gym->longitude), 1)
                : null;

            return [
                'id' => $gym->id,
                'name' => $gym->name,
                'city' => $gym->city,
                'province' => $gym->province,
                'zip_code' => $gym->zip_code,
                'disciplines' => $gym->disciplines ?? [],
                'activity_types' => $gym->activity_types ?? [],
                'tier' => $gym->approved_tier,
                'badge' => config("verification.tiers.{$gym->approved_tier}.badge"),
                'latitude' => $gym->latitude,
                'longitude' => $gym->longitude,
                'distance_km' => $distance,
                'service_names' => $gym->gymServices->pluck('name')->values()->all(),
                'min_price' => $this->minPrice($gym->membershipPlans),
            ];
        });

        if (($data['sort'] ?? 'name') === 'distance') {
            $rows = $rows->sort(function (array $left, array $right) {
                if ($left['distance_km'] === null && $right['distance_km'] === null) {
                    return strcasecmp($left['name'], $right['name']);
                }

                if ($left['distance_km'] === null) {
                    return 1;
                }

                if ($right['distance_km'] === null) {
                    return -1;
                }

                return $left['distance_km'] <=> $right['distance_km']
                    ?: strcasecmp($left['name'], $right['name']);
            });
        }

        $rows = $rows->values();

        return response()->json([
            'gyms' => $rows,
            'total' => $rows->count(),
        ]);
    }

    public function show(Gym $gym): JsonResponse
    {
        abort_unless($gym->status === GymStatus::Approved, 404);

        $services = GymService::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereNotNull('published_at')
            ->with(['photos' => fn ($photos) => $photos->withoutGlobalScope('gym')->orderBy('sort')])
            ->orderBy('name')
            ->get();

        $plans = MembershipPlan::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereNotNull('published_at')
            ->with([
                'addons' => fn ($addons) => $addons->withoutGlobalScope('gym'),
                'addons.service' => fn ($services) => $services->withoutGlobalScope('gym'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'badge' => config("verification.tiers.{$gym->approved_tier}.badge"),
                'tier' => $gym->approved_tier,
                'address_line' => $gym->address_line,
                'barangay' => $gym->barangay,
                'city' => $gym->city,
                'province' => $gym->province,
                'zip_code' => $gym->zip_code,
                'latitude' => $gym->latitude,
                'longitude' => $gym->longitude,
                'contact_phone' => $gym->contact_phone,
                'contact_email' => $gym->contact_email,
                'disciplines' => $gym->disciplines ?? [],
                'activity_types' => $gym->activity_types ?? [],
                'operating_hours' => $gym->operating_hours ?? [],
                'working_days' => $gym->workingDays(),
                'policies' => $gym->policies,
                'facilities' => $gym->facilities ?? [],
                'services' => $services->map(fn (GymService $service) => $this->servicePayload($service, $gym))->all(),
                'plans' => $plans->map(fn (MembershipPlan $plan) => $this->planPayload($plan))->all(),
                'coaches' => $this->coaches($gym),
            ],
        ]);
    }

    private function servicePayload(GymService $service, Gym $gym): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'discipline' => $service->discipline,
            'activity_type' => $service->activity_type,
            'activity_type_label' => $gym->activityTypeLabel($service->activity_type),
            'overview' => $service->overview,
            'level' => $service->level,
            'duration_minutes' => $service->duration_minutes,
            'frequency' => $service->frequency,
            'included' => $service->included ?? [],
            'bring' => $service->bring ?? [],
            'price' => $service->price,
            'price_included' => $service->price_included,
            'cover_photo_url' => $service->cover_photo_path
                ? Storage::disk('public')->url($service->cover_photo_path)
                : null,
            'photo_urls' => $service->photos
                ->map(fn (GymServicePhoto $photo) => Storage::disk('public')->url($photo->path))
                ->values()
                ->all(),
        ];
    }

    private function planPayload(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'billing_type' => $plan->billing_type?->value,
            'duration_months' => $plan->duration_months,
            'session_count' => $plan->session_count,
            'total_fee' => $plan->total_fee,
            'registration_included' => $plan->registration_included,
            'inclusions' => $plan->inclusions ?? [],
            'policy_validity' => $plan->policy_validity,
            'policy_freezing' => $plan->policy_freezing,
            'policy_renewal' => $plan->policy_renewal,
            'policy_cancellation' => $plan->policy_cancellation,
            'max_freeze_days' => $plan->max_freeze_days,
            'refundable' => $plan->refundable,
            'addons' => $plan->addons->map(fn (MembershipPlanAddon $addon) => [
                'id' => $addon->id,
                'service_id' => $addon->gym_service_id,
                'service_name' => $addon->service?->name,
                'price' => $addon->price,
                'included' => false,
            ])->values()->all(),
        ];
    }

    /** R-23: only public coach profiles who still hold a staff role in this gym. */
    private function coaches(Gym $gym): array
    {
        $staffIds = $gym->people()
            ->wherePivotIn('role', [GymPersonType::Employee->value, GymPersonType::Owner->value])
            ->pluck('users.id');

        return EmployeeProfile::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->where('coach_public', true)
            ->whereIn('user_id', $staffIds)
            ->with('user')
            ->get()
            ->filter(fn (EmployeeProfile $profile) => $profile->user !== null && $profile->isCoach())
            ->sortBy(fn (EmployeeProfile $profile) => mb_strtolower(
                $profile->user->first_name.' '.$profile->user->last_name,
            ))
            ->map(fn (EmployeeProfile $profile) => [
                'id' => $profile->user->id,
                'first_name' => $profile->user->first_name,
                'last_name' => $profile->user->last_name,
                'photo_url' => $profile->coach_photo_path
                    ? Storage::disk('public')->url($profile->coach_photo_path)
                    : ($profile->user->photo_path
                        ? Storage::disk('public')->url($profile->user->photo_path)
                        : null),
                'coach_experience' => $profile->coach_experience,
                'coach_rank' => $profile->coach_rank,
                'coach_specialisation' => $profile->coach_specialisation,
                'coach_bio' => $profile->coach_bio,
            ])
            ->values()
            ->all();
    }

    private function minPrice(Collection $plans): ?string
    {
        $fees = $plans->pluck('total_fee')->filter(fn ($fee) => $fee !== null);

        if ($fees->isEmpty()) {
            return null;
        }

        return number_format((float) $fees->min(), 2, '.', '');
    }

    private function distanceKm(float $lat, float $lng, float $gymLat, float $gymLng): float
    {
        $earthKm = 6371.0;
        $latDelta = deg2rad($gymLat - $lat);
        $lngDelta = deg2rad($gymLng - $lng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat)) * cos(deg2rad($gymLat)) * sin($lngDelta / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
