<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'vacation',
            'year' => now()->year,
            'allowance' => config('hr.leave_types.vacation.days', 15),
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
