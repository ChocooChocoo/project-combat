<?php

namespace Database\Factories;

use App\Enums\PayBasis;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'started_on' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
            'pay_basis' => PayBasis::Monthly,
            'monthly_rate' => fake()->numberBetween(12000, 30000),
            'per_session_rate' => null,
            'coach_public' => false,
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
