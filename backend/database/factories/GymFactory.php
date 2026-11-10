<?php

namespace Database\Factories;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Gym',
        ];
    }

    /** A draft application with every profile field filled in. */
    public function application(User $applicant, int $tier = 1): static
    {
        return $this->state(fn () => [
            'applicant_id' => $applicant->id,
            'status' => GymStatus::Draft,
            'tier' => $tier,
            'address_line' => '12 Rizal Street',
            'barangay' => 'Burol',
            'city' => 'Dasmariñas',
            'province' => 'Cavite',
            'zip_code' => '4114',
            'latitude' => 14.3294,
            'longitude' => 120.9367,
            'contact_phone' => '09171234567',
            'contact_email' => fake()->safeEmail(),
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']],
            'disciplines' => ['boxing'],
            'services' => ['Group classes'],
            'facilities' => ['Boxing ring'],
        ]);
    }

    public function approved(int $tier = 1): static
    {
        return $this->state(fn () => ['status' => GymStatus::Approved, 'tier' => $tier, 'approved_tier' => $tier]);
    }
}
