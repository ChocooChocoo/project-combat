<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymHoliday;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymHoliday>
 */
class GymHolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'name' => fake()->randomElement(['Gym anniversary', 'Local fiesta', 'Maintenance day']),
        ];
    }

    /** Gym records need a current gym (BelongsToGym); this state sets it. */
    public function forGym(Gym $gym): static
    {
        app(CurrentGym::class)->set($gym);

        return $this->state(fn () => ['gym_id' => $gym->id]);
    }
}
