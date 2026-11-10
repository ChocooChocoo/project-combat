<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->randomElement(['Maria', 'Ramon', 'Andrea', 'Carlo', 'Paolo', 'Sofia', 'Gabriel', 'Patricia', 'Jomar', 'Bianca', 'Marisol', 'Miguel', 'Camille', 'Rafael', 'Althea']),
            'last_name' => fake()->randomElement(['Santiago', 'Villanueva', 'Lim', 'Mendoza', 'Reyes', 'Ramirez', 'Tan', 'Gomez', 'Aquino', 'Ramos', 'Dela Cruz', 'Santos', 'Bautista', 'Bumanglag', 'Uy']),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123'),
            'remember_token' => Str::random(10),
            'account_type' => 'member',
        ];
    }

    /**
     * Indicate that the account is a Gym Owner account.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'owner',
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
