<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\GymService;
use App\Support\CurrentGym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymService>
 */
class GymServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Boxing Fundamentals', 'Muay Thai Intro', 'Open Mat']),
            'discipline' => 'boxing',
            'activity_type' => 'classes',
            'overview' => fake()->paragraph(),
            'level' => 'all',
            'duration_minutes' => 60,
            'frequency' => 'weekly',
            'included' => ['Wraps', 'Gloves'],
            'bring' => ['Water bottle'],
            'price' => 500,
            'price_included' => false,
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
