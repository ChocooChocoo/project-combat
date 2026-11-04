<?php

namespace Database\Factories;

use App\Models\GymPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymPreference>
 */
class GymPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'discipline' => 'boxing',
            'min_price' => 500,
            'max_price' => 2500,
            'preferred_times' => ['evening'],
        ];
    }
}
