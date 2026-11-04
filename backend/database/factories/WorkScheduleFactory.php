<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekday' => 'mon',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
