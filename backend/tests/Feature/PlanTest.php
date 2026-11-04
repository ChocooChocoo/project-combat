<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\GymService;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanAddon;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-29: membership plans, billing by time or sessions, add-ons and publishing. */
class PlanTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function give(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $role = GymRole::create(['name' => $name, 'permissions' => $permissions]);
        $role->users()->attach($user->id);
        $this->app->forgetScopedInstances();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly Unlimited',
            'billing_type' => 'time',
            'duration_months' => 1,
            'total_fee' => 1500,
            'registration_included' => true,
            'inclusions' => ['All group classes', 'Locker use'],
            'policy_validity' => 'Valid from the first paid month.',
            'policy_freezing' => 'Freeze once a year for up to 30 days.',
            'policy_renewal' => 'Renews monthly until cancelled.',
            'policy_cancellation' => 'Cancel with 15 days notice.',
        ], $overrides);
    }

    private function createPlan(array $overrides = []): MembershipPlan
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload($overrides))
            ->assertCreated()
            ->json('plan.id');

        app(CurrentGym::class)->set($this->gym);

        return MembershipPlan::findOrFail($id);
    }

    public function test_time_and_session_plans_require_their_own_length_field(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('plan.billing_type', 'time')
            ->assertJsonPath('plan.duration_months', 1)
            ->assertJsonPath('plan.session_count', null);

        $time = $this->payload();
        unset($time['duration_months']);

        $this->actingAs($this->owner)->postJson($this->url('plans'), $time)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_months');

        $sessions = $this->payload([
            'name' => '10-Session Pass',
            'billing_type' => 'sessions',
            'session_count' => 10,
        ]);
        unset($sessions['session_count']);

        $this->actingAs($this->owner)->postJson($this->url('plans'), $sessions)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session_count');

        $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload([
            'name' => '10-Session Pass',
            'billing_type' => 'sessions',
            'session_count' => 10,
        ]))->assertCreated()
            ->assertJsonPath('plan.billing_type', 'sessions')
            ->assertJsonPath('plan.session_count', 10)
            ->assertJsonPath('plan.duration_months', null);
    }

    public function test_addons_must_be_services_of_this_gym_and_appear_as_not_included(): void
    {
        $own = GymService::factory()->forGym($this->gym)->create(['name' => 'Private Coaching']);

        $other = Gym::factory()->create();
        $foreign = GymService::factory()->forGym($other)->create();
        app(CurrentGym::class)->set($this->gym);

        $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload([
            'addons' => [['service_id' => $foreign->id, 'price' => 200]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('addons.0.service_id');

        $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload([
            'addons' => [
                ['service_id' => $own->id, 'price' => 200],
                ['service_id' => $own->id, 'price' => 300],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('addons.1.service_id');

        $this->actingAs($this->owner)->postJson($this->url('plans'), $this->payload([
            'addons' => [['service_id' => $own->id, 'price' => 200]],
        ]))->assertCreated()
            ->assertJsonPath('plan.addons.0.service_id', $own->id)
            ->assertJsonPath('plan.addons.0.service_name', 'Private Coaching')
            ->assertJsonPath('plan.addons.0.price', '200.00')
            ->assertJsonPath('plan.addons.0.included', false);
    }

    public function test_index_shows_plan_addon_service_names(): void
    {
        $service = GymService::factory()->forGym($this->gym)->create(['name' => 'Open Mat']);
        $this->createPlan(['addons' => [['service_id' => $service->id, 'price' => 150]]]);

        $this->actingAs($this->owner)->getJson($this->url('plans'))
            ->assertOk()
            ->assertJsonCount(1, 'plans')
            ->assertJsonPath('plans.0.addons.0.service_name', 'Open Mat')
            ->assertJsonPath('plans.0.addons.0.included', false);
    }

    public function test_update_replaces_the_addons_and_keeps_one_billing_length(): void
    {
        $first = GymService::factory()->forGym($this->gym)->create(['name' => 'First']);
        $second = GymService::factory()->forGym($this->gym)->create(['name' => 'Second']);

        $plan = $this->createPlan(['addons' => [['service_id' => $first->id, 'price' => 100]]]);

        $this->actingAs($this->owner)->putJson($this->url("plans/{$plan->id}"), $this->payload([
            'name' => 'Annual Unlimited',
            'duration_months' => 12,
            'addons' => [['service_id' => $second->id, 'price' => 300]],
        ]))->assertOk()
            ->assertJsonPath('plan.name', 'Annual Unlimited')
            ->assertJsonCount(1, 'plan.addons')
            ->assertJsonPath('plan.addons.0.service_id', $second->id);

        app(CurrentGym::class)->set($this->gym);
        $this->assertSame(1, MembershipPlanAddon::count());
        $this->assertSame($second->id, MembershipPlanAddon::first()->gym_service_id);
    }

    public function test_publishing_needs_a_total_fee_inclusions_and_all_four_policies(): void
    {
        $plan = MembershipPlan::factory()->forGym($this->gym)->create([
            'inclusions' => [],
            'policy_renewal' => '',
        ]);

        $this->actingAs($this->owner)->postJson($this->url("plans/{$plan->id}/publish"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inclusions', 'policy_renewal']);

        $this->assertNull($plan->fresh()->published_at);
    }

    public function test_a_complete_plan_publishes_and_unpublishes(): void
    {
        $plan = $this->createPlan();

        $this->actingAs($this->owner)->postJson($this->url("plans/{$plan->id}/publish"))->assertOk();

        $this->assertNotNull($plan->fresh()->published_at);

        $this->actingAs($this->owner)->postJson($this->url("plans/{$plan->id}/unpublish"))
            ->assertOk();

        $this->assertNull($plan->fresh()->published_at);
    }

    public function test_members_view_permission_can_list_plans_but_not_change_them(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('plans'))->assertForbidden();
        $this->actingAs($this->employee)->postJson($this->url('plans'), $this->payload())->assertForbidden();

        $this->give($this->employee, ['members' => 'view'], 'Members viewer');

        $this->actingAs($this->employee)->getJson($this->url('plans'))->assertOk();
        $this->actingAs($this->employee)->postJson($this->url('plans'), $this->payload())->assertForbidden();
    }

    public function test_deleting_a_plan_removes_its_addons(): void
    {
        $service = GymService::factory()->forGym($this->gym)->create();
        $plan = $this->createPlan(['addons' => [['service_id' => $service->id, 'price' => 100]]]);

        $this->actingAs($this->owner)->deleteJson($this->url("plans/{$plan->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Plan deleted.');

        app(CurrentGym::class)->set($this->gym);
        $this->assertDatabaseMissing('membership_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('membership_plan_addons', ['membership_plan_id' => $plan->id]);
    }

    public function test_a_plan_from_another_gym_is_not_found(): void
    {
        $other = Gym::factory()->create();
        $foreign = MembershipPlan::factory()->forGym($other)->create();
        app(CurrentGym::class)->set($this->gym);

        $this->actingAs($this->owner)->putJson($this->url("plans/{$foreign->id}"), $this->payload())
            ->assertNotFound();
        $this->actingAs($this->owner)->postJson($this->url("plans/{$foreign->id}/publish"))
            ->assertNotFound();
        $this->actingAs($this->owner)->deleteJson($this->url("plans/{$foreign->id}"))
            ->assertNotFound();
    }
}
