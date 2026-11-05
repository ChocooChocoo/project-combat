<?php

namespace App\Http\Controllers\Gym;

use App\Enums\PlanBilling;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-29: membership plans and publishing. */
class PlanController extends Controller
{
    public function index(Gym $gym): JsonResponse
    {
        return response()->json([
            'plans' => $gym->membershipPlans()
                ->with('addons.service')
                ->orderBy('name')
                ->get()
                ->map(fn (MembershipPlan $plan) => $this->payload($plan))
                ->all(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        [$data, $addons] = $this->validated($request, $gym);

        $plan = DB::transaction(function () use ($gym, $data, $addons) {
            $plan = $gym->membershipPlans()->create($data);

            foreach ($addons as $addon) {
                $plan->addons()->create([
                    'gym_service_id' => $addon['service_id'],
                    'price' => $addon['price'],
                ]);
            }

            return $plan;
        });

        return response()->json(['plan' => $this->payload($plan)], 201);
    }

    public function update(Request $request, Gym $gym, int $plan): JsonResponse
    {
        $model = $gym->membershipPlans()->findOrFail($plan);
        [$data, $addons, $replaceAddons] = $this->validated($request, $gym, $model);

        DB::transaction(function () use ($model, $data, $addons, $replaceAddons) {
            $model->update($data);

            // A plan is either priced by time or by sessions, never both.
            if ($model->billing_type === PlanBilling::Time) {
                $model->forceFill(['session_count' => null])->save();
            } else {
                $model->forceFill(['duration_months' => null])->save();
            }

            if ($replaceAddons) {
                $model->addons()->delete();

                foreach ($addons as $addon) {
                    $model->addons()->create([
                        'gym_service_id' => $addon['service_id'],
                        'price' => $addon['price'],
                    ]);
                }
            }
        });

        return response()->json(['plan' => $this->payload($model->fresh())]);
    }

    public function destroy(Gym $gym, int $plan): JsonResponse
    {
        $model = $gym->membershipPlans()->findOrFail($plan);
        $model->addons()->delete();
        $model->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    public function publish(Gym $gym, int $plan): JsonResponse
    {
        $model = $gym->membershipPlans()->findOrFail($plan);
        $errors = $this->publishErrors($model);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $model->forceFill(['published_at' => now()])->save();

        return response()->json(['plan' => $this->payload($model)]);
    }

    public function unpublish(Gym $gym, int $plan): JsonResponse
    {
        $model = $gym->membershipPlans()->findOrFail($plan);
        $model->forceFill(['published_at' => null])->save();

        return response()->json(['plan' => $this->payload($model)]);
    }

    /** @return array{0: array, 1: array, 2: bool} */
    private function validated(Request $request, Gym $gym, ?MembershipPlan $plan = null): array
    {
        $billing = $request->input('billing_type', $plan?->billing_type?->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'billing_type' => ['required', Rule::in(array_keys(config('offer.plan_billing')))],
            'duration_months' => [
                'nullable', 'integer', 'min:1', 'max:120',
                Rule::requiredIf($billing === PlanBilling::Time->value),
            ],
            'session_count' => [
                'nullable', 'integer', 'min:1', 'max:365',
                Rule::requiredIf($billing === PlanBilling::Sessions->value),
            ],
            'total_fee' => ['required', 'numeric', 'min:0'],
            'registration_included' => ['sometimes', 'boolean'],
            'inclusions' => ['required', 'array', 'min:1'],
            'inclusions.*' => ['string', 'max:255'],
            'policy_validity' => ['required', 'string', 'max:5000'],
            'policy_freezing' => ['required', 'string', 'max:5000'],
            'policy_renewal' => ['required', 'string', 'max:5000'],
            'policy_cancellation' => ['required', 'string', 'max:5000'],
            // R-36: empty means the plan cannot be frozen.
            'max_freeze_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'refundable' => ['sometimes', 'boolean'],
            'addons' => ['sometimes', 'array'],
            'addons.*' => ['array:service_id,price'],
            'addons.*.service_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('gym_services', 'id')->where('gym_id', $gym->id),
            ],
            'addons.*.price' => ['required', 'numeric', 'min:0'],
        ], [
            'addons.*.service_id.exists' => 'Choose a service from this gym.',
            'addons.*.service_id.distinct' => 'Each service can be added only once.',
        ]);

        $replaceAddons = array_key_exists('addons', $data);
        $addons = $data['addons'] ?? [];
        unset($data['addons']);

        if (! $plan) {
            $data['registration_included'] = $data['registration_included'] ?? false;
            $data['refundable'] = $data['refundable'] ?? false;

            // Only the length that belongs to the chosen billing type is kept.
            if ($data['billing_type'] === PlanBilling::Sessions->value) {
                $data['duration_months'] = null;
            } else {
                $data['session_count'] = null;
            }
        }

        return [$data, $addons, $replaceAddons];
    }

    private function publishErrors(MembershipPlan $plan): array
    {
        $errors = [];

        if (blank($plan->total_fee)) {
            $errors['total_fee'] = 'Add the total fee.';
        }

        if (blank($plan->inclusions)) {
            $errors['inclusions'] = 'List at least one thing the fee covers.';
        }

        foreach (['policy_validity', 'policy_freezing', 'policy_renewal', 'policy_cancellation'] as $field) {
            if (blank($plan->{$field})) {
                $errors[$field] = 'Write this policy before publishing.';
            }
        }

        return $errors;
    }

    private function payload(MembershipPlan $plan): array
    {
        $addons = $plan->relationLoaded('addons')
            ? $plan->addons
            : $plan->addons()->with('service')->get();

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
            'addons' => $addons->map(fn (MembershipPlanAddon $addon) => [
                'id' => $addon->id,
                'service_id' => $addon->gym_service_id,
                'service_name' => $addon->service?->name,
                'price' => $addon->price,
                'included' => false,
            ])->all(),
            'published_at' => $plan->published_at?->toIso8601String(),
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
