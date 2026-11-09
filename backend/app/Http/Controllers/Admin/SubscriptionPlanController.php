<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Support\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-102 to R-104: the administrator's plans, what each may use, and the trial length. */
class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = Gym::whereNotNull('subscription_plan_id')->where('paid_until', '>', now())
            ->selectRaw('subscription_plan_id, count(*) as total')->groupBy('subscription_plan_id')
            ->pluck('total', 'subscription_plan_id');

        return response()->json([
            'plans' => SubscriptionPlan::orderByDesc('is_free')->orderBy('monthly_price')->orderBy('name')->get()
                ->map(fn (SubscriptionPlan $plan) => [...self::payload($plan), 'paying_gyms' => (int) ($counts[$plan->id] ?? 0)]),
            'modules' => collect(Subscription::MODULE_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'trial_days' => Subscription::trialDays(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = SubscriptionPlan::create($this->validated($request));

        return response()->json(['plan' => self::payload($plan)], 201);
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $data = $this->validated($request, $plan);
        if ($plan->is_free) {
            $data['monthly_price'] = 0;
        }
        $plan->update($data);

        return response()->json(['plan' => self::payload($plan)]);
    }

    /** Retired plans cannot be chosen; gyms already on one keep it until their month ends. */
    public function toggleRetired(SubscriptionPlan $plan): JsonResponse
    {
        abort_if($plan->is_free, 422, 'The free plan cannot be retired.');
        $plan->forceFill(['retired_at' => $plan->retired_at ? null : now()])->save();

        return response()->json(['plan' => self::payload($plan)]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['trial_days' => ['required', 'integer', 'between:1,365']]);
        PlatformSetting::put('trial_days', $data['trial_days']);

        return response()->json(['trial_days' => Subscription::trialDays()]);
    }

    /** @return array<string, mixed> */
    public static function payload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'inclusions' => $plan->inclusions,
            'modules' => array_values(array_intersect(Subscription::lockableModules(), $plan->modules ?? [])),
            'is_free' => $plan->is_free,
            'retired' => $plan->retired_at !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('subscription_plans', 'name')->ignore($plan?->id)],
            // PayMongo's smallest online payment is PHP 20.
            'monthly_price' => ['required', 'numeric', $plan?->is_free ? 'min:0' : 'min:20', 'max:999999'],
            'inclusions' => ['nullable', 'string', 'max:2000'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', 'distinct', Rule::in(Subscription::lockableModules())],
        ]);
    }
}
