<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-18 and R-30: the public gym page shows only approved, published, public data. */
class PublicGymTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function approvedGym(array $attributes = []): Gym
    {
        return Gym::factory()->approved(2)->create(array_merge([
            'name' => 'Manila Combat Club',
            'address_line' => '1 Mabini Street',
            'barangay' => 'Poblacion',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'contact_phone' => '09170000000',
            'contact_email' => 'hello@gym.test',
            'disciplines' => ['boxing', 'muay_thai'],
            'activity_types' => ['classes', 'sparring'],
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']],
            'facilities' => ['Ring', 'Locker room'],
        ], $attributes));
    }

    private function service(Gym $gym, array $attributes = []): GymService
    {
        app(CurrentGym::class)->set($gym);

        return GymService::factory()->forGym($gym)->create(array_merge([
            'name' => 'Boxing Fundamentals',
            'published_at' => now(),
            'cover_photo_path' => 'gym-services/boxing-cover.jpg',
        ], $attributes));
    }

    private function plan(Gym $gym, array $attributes = [], array $addons = []): MembershipPlan
    {
        app(CurrentGym::class)->set($gym);

        $plan = MembershipPlan::factory()->forGym($gym)->create(array_merge([
            'name' => 'Monthly Unlimited',
            'total_fee' => 1500,
            'published_at' => now(),
        ], $attributes));

        foreach ($addons as $serviceId => $price) {
            $plan->addons()->create(['gym_service_id' => $serviceId, 'price' => $price]);
        }

        return $plan;
    }

    private function coach(Gym $gym, array $userAttributes = [], array $profileAttributes = []): User
    {
        $coach = User::factory()->create($userAttributes);
        $gym->addPerson($coach, GymPersonType::Employee);

        app(CurrentGym::class)->set($gym);
        EmployeeProfile::factory()->forGym($gym)->create(array_merge([
            'user_id' => $coach->id,
            'coach_public' => true,
            'coach_experience' => 'Eight years of amateur boxing.',
            'coach_rank' => 'Head coach',
            'coach_specialisation' => 'Boxing',
            'coach_bio' => 'Former national team boxer.',
        ], $profileAttributes));

        return $coach;
    }

    public function test_only_approved_gyms_are_listed_and_a_draft_gym_is_not_found(): void
    {
        $approved = $this->approvedGym(['name' => 'Approved Club']);
        $draft = Gym::factory()->application($this->user)->create(['name' => 'Draft Club']);
        $this->app->forgetScopedInstances();

        $this->getJson('/api/gyms')->assertOk()
            ->assertJsonCount(1, 'gyms')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('gyms.0.id', $approved->id)
            ->assertJsonPath('gyms.0.tier', 2)
            ->assertJsonPath('gyms.0.badge', 'Tier 2 verified')
            ->assertJsonMissingPath('gyms.0.applicant_id');

        $this->getJson("/api/gyms/{$draft->id}")->assertNotFound();
        $this->getJson("/api/gyms/{$approved->id}")->assertOk()
            ->assertJsonPath('gym.id', $approved->id)
            ->assertJsonPath('gym.name', 'Approved Club');
    }

    public function test_the_public_page_shows_only_published_services_and_plans(): void
    {
        $gym = $this->approvedGym();
        $published = $this->service($gym);
        $this->service($gym, ['name' => 'Draft Service', 'published_at' => null]);
        $published->photos()->create(['path' => 'gym-services/gallery-one.jpg', 'sort' => 1]);
        $published->photos()->create(['path' => 'gym-services/gallery-two.jpg', 'sort' => 2]);

        $this->plan($gym, ['name' => 'Published Plan'], [$published->id => 500]);
        $this->plan($gym, ['name' => 'Draft Plan', 'published_at' => null]);
        $this->app->forgetScopedInstances();

        $this->getJson("/api/gyms/{$gym->id}")->assertOk()
            ->assertJsonCount(1, 'gym.services')
            ->assertJsonPath('gym.services.0.name', 'Boxing Fundamentals')
            ->assertJsonPath('gym.services.0.cover_photo_url', Storage::disk('public')->url('gym-services/boxing-cover.jpg'))
            ->assertJsonPath('gym.services.0.photo_urls', [
                Storage::disk('public')->url('gym-services/gallery-one.jpg'),
                Storage::disk('public')->url('gym-services/gallery-two.jpg'),
            ])
            ->assertJsonCount(1, 'gym.plans')
            ->assertJsonPath('gym.plans.0.name', 'Published Plan')
            ->assertJsonPath('gym.plans.0.addons.0.service_id', $published->id)
            ->assertJsonPath('gym.plans.0.addons.0.service_name', 'Boxing Fundamentals')
            ->assertJsonPath('gym.plans.0.addons.0.price', '500.00')
            ->assertJsonPath('gym.plans.0.addons.0.included', false);
    }

    public function test_the_public_page_returns_only_public_fields(): void
    {
        $gym = $this->approvedGym();
        $this->coach($gym, ['email' => 'coach@example.test'], [
            'monthly_rate' => 20000,
            'per_session_rate' => 800,
        ]);
        $this->app->forgetScopedInstances();

        $response = $this->getJson("/api/gyms/{$gym->id}")->assertOk();
        $payload = $response->json('gym');

        $this->assertEqualsCanonicalizing([
            'id', 'name', 'badge', 'tier', 'address_line', 'barangay', 'city', 'province',
            'latitude', 'longitude', 'contact_phone', 'contact_email', 'disciplines',
            'activity_types', 'operating_hours', 'working_days', 'policies', 'facilities',
            'services', 'plans', 'coaches',
        ], array_keys($payload));

        foreach ([
            'applicant_id', 'status', 'decision_reason', 'checklist', 'documents',
        ] as $private) {
            $this->assertArrayNotHasKey($private, $payload);
        }

        $this->assertEqualsCanonicalizing([
            'id', 'first_name', 'last_name', 'photo_url', 'coach_experience',
            'coach_rank', 'coach_specialisation', 'coach_bio',
        ], array_keys($payload['coaches'][0]));

        $this->assertArrayNotHasKey('email', $payload['coaches'][0]);
        $this->assertArrayNotHasKey('monthly_rate', $payload['coaches'][0]);
        $this->assertArrayNotHasKey('per_session_rate', $payload['coaches'][0]);
    }

    public function test_only_public_coaches_who_still_hold_the_employee_role_are_shown(): void
    {
        $gym = $this->approvedGym();
        $this->coach($gym, [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'photo_path' => 'profile-photos/ana.jpg',
        ]);
        $this->coach($gym, [
            'first_name' => 'Zara',
            'last_name' => 'Cruz',
            'photo_path' => 'profile-photos/zara.jpg',
        ], [
            'coach_photo_path' => 'coach-photos/zara.jpg',
        ]);
        $this->coach($gym, ['first_name' => 'Noel', 'last_name' => 'Santos'], [
            'coach_public' => false,
        ]);
        $former = $this->coach($gym, ['first_name' => 'Former', 'last_name' => 'Coach']);
        $gym->people()->detach($former->id);
        $this->coach($gym, ['first_name' => 'Desk', 'last_name' => 'Staff'], [
            'coach_experience' => null, 'coach_rank' => null, 'coach_specialisation' => null,
            'coach_bio' => null, 'coach_photo_path' => null,
        ]);
        $this->app->forgetScopedInstances();

        $response = $this->getJson("/api/gyms/{$gym->id}")->assertOk()
            ->assertJsonCount(2, 'gym.coaches')
            ->assertJsonPath('gym.coaches.0.first_name', 'Ana')
            ->assertJsonPath('gym.coaches.0.photo_url', Storage::disk('public')->url('profile-photos/ana.jpg'))
            ->assertJsonPath('gym.coaches.1.first_name', 'Zara')
            ->assertJsonPath('gym.coaches.1.photo_url', Storage::disk('public')->url('coach-photos/zara.jpg'));

        $this->assertSame(
            ['Ana', 'Zara'],
            collect($response->json('gym.coaches'))->pluck('first_name')->all(),
        );
    }
}
