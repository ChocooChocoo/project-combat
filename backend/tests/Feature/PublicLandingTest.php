<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\PlanBilling;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\MembershipPlan;
use App\Models\Room;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The landing page reads approved gyms and published offers without a sign-in. */
class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    private function approvedGym(array $attributes = []): Gym
    {
        return Gym::factory()->approved(2)->create(array_merge([
            'name' => 'Manila Combat Club',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'disciplines' => ['boxing', 'muay_thai'],
        ], $attributes));
    }

    private function service(Gym $gym, array $attributes = []): GymService
    {
        app(CurrentGym::class)->set($gym);

        return GymService::factory()->forGym($gym)->create(array_merge([
            'name' => 'Boxing Fundamentals',
            'discipline' => 'boxing',
            'price' => 450,
            'published_at' => now(),
        ], $attributes));
    }

    private function plan(Gym $gym, array $attributes = []): MembershipPlan
    {
        app(CurrentGym::class)->set($gym);

        return MembershipPlan::factory()->forGym($gym)->create(array_merge([
            'name' => 'Monthly Unlimited',
            'billing_type' => PlanBilling::Time,
            'duration_months' => 1,
            'total_fee' => 1500,
            'inclusions' => ['Unlimited group classes', 'Locker use'],
            'published_at' => now(),
        ], $attributes));
    }

    private function coach(Gym $gym, array $attributes = []): User
    {
        $coach = User::factory()->create([
            'first_name' => $attributes['first_name'] ?? 'Ana',
            'last_name' => $attributes['last_name'] ?? 'Reyes',
            'email' => $attributes['email'] ?? fake()->unique()->safeEmail(),
        ]);
        $gym->addPerson($coach, GymPersonType::Employee);

        app(CurrentGym::class)->set($gym);
        EmployeeProfile::factory()->forGym($gym)->create(array_merge([
            'user_id' => $coach->id,
            'coach_public' => true,
            'coach_rank' => 'Head coach',
            'coach_specialisation' => 'Boxing',
            'coach_experience' => 'Eight years of amateur boxing.',
        ], $attributes));

        return $coach;
    }

    private function trainingSession(Gym $gym, User $coach): TrainingSession
    {
        app(CurrentGym::class)->set($gym);
        $room = Room::factory()->forGym($gym)->create();

        return TrainingSession::create([
            'type' => 'class',
            'name' => 'Boxing Fundamentals',
            'discipline' => 'boxing',
            'level' => 'all',
            'coach_id' => $coach->id,
            'room_id' => $room->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 8,
        ]);
    }

    public function test_the_landing_payload_needs_no_sign_in_and_counts_only_public_records(): void
    {
        $gym = $this->approvedGym();
        $this->service($gym);
        $this->service($gym, ['name' => 'Draft Service', 'published_at' => null]);
        $this->plan($gym);
        $this->plan($gym, ['name' => 'Draft Plan', 'published_at' => null]);
        $coach = $this->coach($gym);
        $this->coach($gym, ['coach_public' => false]);
        $this->trainingSession($gym, $coach);

        $this->app->forgetScopedInstances();

        $this->getJson('/api/public/landing')->assertOk()
            ->assertJsonPath('stats.gyms', 1)
            ->assertJsonPath('stats.cities', 1)
            ->assertJsonPath('stats.disciplines', 2)
            ->assertJsonPath('stats.services', 1)
            ->assertJsonPath('stats.plans', 1)
            ->assertJsonPath('stats.coaches', 1)
            ->assertJsonPath('stats.upcoming_sessions', 1)
            ->assertJsonPath('stats.plan_fee_min', '1500.00')
            ->assertJsonPath('gyms.0.name', 'Manila Combat Club')
            ->assertJsonPath('gyms.0.badge', 'Tier 2 verified')
            ->assertJsonPath('gyms.0.services', 1)
            ->assertJsonPath('gyms.0.plan_from', '1500.00')
            ->assertJsonPath('plans.0.name', 'Monthly Unlimited')
            ->assertJsonPath('coaches.0.name', 'Ana Reyes')
            ->assertJsonPath('sessions.0.name', 'Boxing Fundamentals');
    }

    public function test_unapproved_gyms_and_unpublished_records_are_left_out(): void
    {
        $this->approvedGym(['name' => 'Live Gym']);

        Gym::factory()->create(['name' => 'Draft Gym', 'disciplines' => ['boxing']]);
        Gym::factory()->application(User::factory()->owner()->create())->create(['name' => 'Applied Gym']);

        $this->app->forgetScopedInstances();

        $response = $this->getJson('/api/public/landing')->assertOk();

        $this->assertSame(['Live Gym'], collect($response->json('gyms'))->pluck('name')->all());
        $this->assertSame(0, $response->json('stats.services'));
        $this->assertSame(0, $response->json('stats.plans'));
        $this->assertSame(0, $response->json('stats.coaches'));
    }

    public function test_the_landing_payload_carries_no_private_contact_or_staff_fields(): void
    {
        $gym = $this->approvedGym();
        $this->plan($gym);
        $this->coach($gym);

        $this->app->forgetScopedInstances();

        $payload = $this->getJson('/api/public/landing')->assertOk()->json();

        $expected = [
            'id', 'name', 'city', 'province', 'tier', 'badge',
            'disciplines', 'services', 'plans', 'plan_from',
        ];
        $this->assertSame($expected, array_keys($payload['gyms'][0]));

        foreach (['email', 'phone', 'contact_email', 'contact_phone', 'monthly_rate', 'per_session_rate', 'medical_file'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($payload));
        }
    }
}
