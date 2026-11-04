<?php

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Models\Gym;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'vacation',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'working_days' => 1,
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::Pending,
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
