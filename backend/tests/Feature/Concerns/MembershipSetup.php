<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;

/** Shared set-up for the Phase 4 tests: a gym, an owner, a published plan and an applicant. */
trait MembershipSetup
{
    use GivesGymRoles;
    protected Gym $gym;

    protected User $owner;

    protected User $applicant;

    protected MembershipPlan $plan;

    protected function setUpMembershipGym(array $plan = []): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->owner()->create();
        $this->applicant = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $this->gym->addPerson($this->owner, GymPersonType::Owner);

        $this->plan = MembershipPlan::factory()->forGym($this->gym)->create([
            'published_at' => now(),
            'total_fee' => 1500,
            'max_freeze_days' => 30,
            'refundable' => true,
            ...$plan,
        ]);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function employeeWith(array $permissions): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, 'Role '.$user->id)->users()->attach($user->id);
        $this->app->forgetScopedInstances();

        return $user;
    }

    protected function applicationPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'plan_id' => $this->plan->id,
            'emergency_contacts' => [['name' => 'Maria Cruz', 'relationship' => 'Mother', 'phone' => '0917 123 4567']],
            'background' => 'Two years of boxing.',
            'current_rank' => 'None',
            'training_history' => 'Weekend classes.',
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'health_notes' => null,
            'waivers' => array_fill_keys(array_keys(config('membership.waivers')), true),
            'signature_name' => 'Ana Cruz',
        ], $overrides);
    }

    protected function apply(?User $user = null): Membership
    {
        $id = $this->actingAs($user ?? $this->applicant)
            ->postJson($this->url('membership-applications'), $this->applicationPayload())
            ->assertCreated()
            ->json('membership.id');

        return $this->membership($id);
    }

    protected function approved(): Membership
    {
        $membership = $this->apply();
        $this->actingAs($this->owner)
            ->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])
            ->assertOk();

        return $this->membership($membership->id);
    }

    protected function active(): Membership
    {
        $membership = $this->approved();
        $this->actingAs($this->owner)
            ->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1500, 'method' => 'cash'])
            ->assertCreated();

        return $this->membership($membership->id);
    }

    protected function membership(int $id): Membership
    {
        return Membership::withoutGlobalScope('gym')->findOrFail($id);
    }
}
