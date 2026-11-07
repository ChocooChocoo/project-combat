<?php

namespace App\Http\Controllers;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Enums\PlanBilling;
use App\Enums\SessionStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\MembershipPlan;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The public landing page reads approved gyms and what they have published.
 * No account, no member records, no money owed: only the same information the
 * public gym page already shows, plus platform totals.
 */
class PublicLandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $gyms = Gym::query()
            ->where('status', GymStatus::Approved)
            ->orderBy('name')
            ->get();

        $gymIds = $gyms->pluck('id');

        $services = GymService::withoutGlobalScope('gym')
            ->whereIn('gym_id', $gymIds)
            ->whereNotNull('published_at')
            ->get();

        $plans = MembershipPlan::withoutGlobalScope('gym')
            ->whereIn('gym_id', $gymIds)
            ->whereNotNull('published_at')
            ->orderBy('total_fee')
            ->get();

        $coaches = $this->coaches($gyms);
        $featuredPlans = $this->featuredPlans($plans);
        $nextSessions = $this->nextSessions($gymIds);
        $disciplines = $this->disciplines($gyms, $services);

        $gymNames = $gyms->pluck('name', 'id');

        return response()->json([
            'stats' => [
                'gyms' => $gyms->count(),
                'cities' => $gyms->pluck('city')->filter()->unique()->count(),
                'disciplines' => $disciplines->count(),
                'services' => $services->count(),
                'plans' => $plans->count(),
                'coaches' => $coaches->count(),
                'upcoming_sessions' => TrainingSession::withoutGlobalScope('gym')
                    ->whereIn('gym_id', $gymIds)
                    ->where('status', SessionStatus::Scheduled)
                    ->where('starts_at', '>=', now())
                    ->count(),
                'plan_fee_min' => $plans->isNotEmpty() ? $this->money($plans->min('total_fee')) : null,
                'plan_fee_max' => $plans->isNotEmpty() ? $this->money($plans->max('total_fee')) : null,
            ],
            'gyms' => $gyms->map(fn (Gym $gym) => [
                'id' => $gym->id,
                'name' => $gym->name,
                'city' => $gym->city,
                'province' => $gym->province,
                'tier' => $gym->approved_tier,
                'badge' => config("verification.tiers.{$gym->approved_tier}.badge"),
                'disciplines' => $gym->disciplines ?? [],
                'services' => $services->where('gym_id', $gym->id)->count(),
                'plans' => $plans->where('gym_id', $gym->id)->count(),
                'plan_from' => $this->planFrom($plans, $gym->id),
            ])->values()->all(),
            'disciplines' => $disciplines->all(),
            'plans' => $featuredPlans->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'gym_id' => $plan->gym_id,
                'gym_name' => $gymNames[$plan->gym_id] ?? '',
                'name' => $plan->name,
                'billing_type' => $plan->billing_type?->value,
                'duration_months' => $plan->duration_months,
                'session_count' => $plan->session_count,
                'total_fee' => $this->money($plan->total_fee),
                'inclusions' => $plan->inclusions ?? [],
            ])->values()->all(),
            'plan_inclusions' => $plans
                ->flatMap(fn (MembershipPlan $plan) => array_slice($plan->inclusions ?? [], 0, 2))
                ->unique()
                ->values()
                ->take(4)
                ->all(),
            'coaches' => $coaches,
            'sessions' => $nextSessions->map(fn (TrainingSession $session) => [
                'id' => $session->id,
                'gym_name' => $gymNames[$session->gym_id] ?? '',
                'name' => $session->name,
                'discipline' => $session->discipline,
                'starts_at' => $session->starts_at->toIso8601String(),
                'ends_at' => $session->ends_at->toIso8601String(),
                'capacity' => $session->capacity,
            ])->values()->all(),
        ]);
    }

    /**
     * A discipline row counts the gyms that teach it and the classes they published.
     * Labels come from the same catalog the discovery filters use.
     */
    private function disciplines(Collection $gyms, Collection $services): Collection
    {
        $labels = config('verification.disciplines');
        $keys = $gyms
            ->flatMap(fn (Gym $gym) => $gym->disciplines ?? [])
            ->filter(fn ($key) => isset($labels[$key]))
            ->unique()
            ->values();

        return $keys
            ->map(function (string $key) use ($gyms, $services, $labels) {
                $gymCount = $gyms->filter(fn (Gym $gym) => in_array($key, $gym->disciplines ?? [], true))->count();
                $classes = $services->where('discipline', $key);

                return [
                    'key' => $key,
                    'label' => $labels[$key],
                    'gyms' => $gymCount,
                    'services' => $classes->count(),
                    'from' => $classes->whereNotNull('price')->isNotEmpty()
                        ? $this->money($classes->whereNotNull('price')->min('price'))
                        : null,
                ];
            })
            ->sortBy([['gyms', 'desc'], ['label', 'asc']])
            ->values();
    }

    /** The cheapest published plan in each gym, so the page shows one real offer per gym. */
    private function featuredPlans(Collection $plans): Collection
    {
        return $plans
            ->groupBy('gym_id')
            ->map(function (Collection $gymPlans) {
                $monthly = $gymPlans->filter(
                    fn (MembershipPlan $plan) => $plan->billing_type === PlanBilling::Time && $plan->duration_months === 1,
                );

                return $monthly->isNotEmpty()
                    ? $monthly->sortBy('total_fee')->first()
                    : $gymPlans->sortBy('total_fee')->first();
            })
            ->sortBy(fn (MembershipPlan $plan) => (float) $plan->total_fee)
            ->values();
    }

    /** Four soonest sessions, enough for a live strip without exposing a full timetable. */
    private function nextSessions(Collection $gymIds): Collection
    {
        return TrainingSession::withoutGlobalScope('gym')
            ->whereIn('gym_id', $gymIds)
            ->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(4)
            ->get();
    }

    /** R-23: only public coach profiles who still hold a staff role in their gym. */
    private function coaches(Collection $gyms): Collection
    {
        return $gyms->flatMap(function (Gym $gym) {
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
                ->map(fn (EmployeeProfile $profile) => [
                    'id' => $profile->user->id,
                    'name' => trim($profile->user->first_name.' '.$profile->user->last_name),
                    'rank' => $profile->coach_rank,
                    'specialisation' => $profile->coach_specialisation,
                    'gym_id' => $gym->id,
                    'gym_name' => $gym->name,
                ]);
        })->sortBy('name')->values();
    }

    private function planFrom(Collection $plans, int $gymId): ?string
    {
        $fees = $plans->where('gym_id', $gymId)->pluck('total_fee');

        return $fees->isEmpty() ? null : $this->money($fees->min());
    }

    private function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
