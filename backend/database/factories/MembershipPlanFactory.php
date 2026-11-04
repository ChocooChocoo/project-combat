<?php

namespace Database\Factories;

use App\Enums\PlanBilling;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Monthly Unlimited', '10-Session Pass', 'Annual Member']),
            'billing_type' => PlanBilling::Time,
            'duration_months' => 1,
            'session_count' => null,
            'total_fee' => 1500,
            'registration_included' => true,
            'inclusions' => ['All group classes', 'Locker use'],
            'policy_validity' => 'Valid from the first paid month.',
            'policy_freezing' => 'Freeze once a year for up to 30 days.',
            'policy_renewal' => 'Renews monthly until cancelled.',
            'policy_cancellation' => 'Cancel with 15 days notice.',
            'published_at' => null,
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
