<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Enums\MembershipStatus;
use App\Models\Customer;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Support\Facades\DB;

/** A gym, owner, employee/member fixtures and helpers for the Packet 6C tests. */
trait CustomerSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $employee;

    protected User $member;

    protected MembershipPlan $plan;

    protected function setUpCustomerGym(): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->employee = User::factory()->create(['first_name' => 'Ella', 'last_name' => 'Santos']);
        $this->member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Torres']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
        $this->gym->addPerson($this->member, GymPersonType::Member);

        app(CurrentGym::class)->set($this->gym);

        $this->plan = MembershipPlan::factory()->forGym($this->gym)->create([
            'published_at' => now(),
            'total_fee' => 1500,
        ]);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function customerData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Rico Dizon',
            'email' => 'rico.dizon@example.test',
            'phone' => '0917 555 0171',
            'source' => 'walk_in',
            'inquiry' => 'Asked about beginner boxing classes.',
            'assigned_to' => $this->employee->id,
        ], $overrides);
    }

    protected function addCustomer(array $overrides = [], ?User $actor = null): Customer
    {
        $id = $this->actingAs($actor ?? $this->owner)
            ->postJson($this->url('customers'), $this->customerData($overrides))
            ->assertCreated()
            ->json('customer.id');

        return $this->customer($id);
    }

    protected function customer(int $id): Customer
    {
        return Customer::withoutGlobalScope('gym')->findOrFail($id);
    }

    protected function customerCount(): int
    {
        return DB::table('customers')->where('gym_id', $this->gym->id)->count();
    }

    protected function membershipFor(User $user, array $overrides = []): Membership
    {
        app(CurrentGym::class)->set($this->gym);

        $membership = new Membership;
        $membership->fill([
            'user_id' => $user->id,
            'membership_plan_id' => $this->plan->id,
            'emergency_contacts' => [['name' => 'Maria Cruz', 'relationship' => 'Parent', 'phone' => '0917 123 4567']],
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'waivers' => array_keys(config('membership.waivers')),
            'signature_name' => trim("{$user->first_name} {$user->last_name}"),
            'signed_on' => today()->toDateString(),
        ]);
        $membership->forceFill(array_replace([
            'status' => MembershipStatus::Applied->value,
        ], $overrides))->save();

        return $membership->refresh();
    }

    protected function activateMembership(Membership $membership): Membership
    {
        $membership->forceFill([
            'status' => MembershipStatus::Active->value,
            'starts_on' => today()->toDateString(),
        ])->save();

        return $membership->refresh();
    }

    protected function giveRole(User $user, array $permissions, string $name = 'Customer role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name.' '.$user->id)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }

    protected function employeeWithRole(array $permissions): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Employee);
        $this->giveRole($user, $permissions, 'Customer test role');

        return $user;
    }

    protected function assertLogicFails(callable $callback): void
    {
        try {
            $callback();
        } catch (\LogicException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected a LogicException.');
    }
}
