<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;
use Tests\Feature\Concerns\GivesGymRoles;
use App\Support\PermissionCatalog;

/** R-07 roles, R-08 owner-made roles ("dynamic RBAC"), R-09 gym separation. */
class AccessTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
    }

    private function url(string $path, ?Gym $gym = null): string
    {
        return '/api/gyms/'.($gym ?? $this->gym)->id.'/'.$path;
    }

    /** Creates a role in a gym directly, the way the owner would. */
    private function role(array $permissions, ?Gym $gym = null, string $name = 'Role'): GymRole
    {
        app(CurrentGym::class)->set($gym ?? $this->gym);
        $role = $this->makeRole($permissions, $name);
        $this->app->forgetScopedInstances();

        return $role;
    }

    private function give(User $user, GymRole ...$roles): void
    {
        foreach ($roles as $role) {
            $role->users()->attach($user->id);
        }
    }

    public function test_platform_admin_is_made_only_by_the_command(): void
    {
        $this->artisan('app:make-admin', ['email' => $this->owner->email])->assertSuccessful();
        $this->assertTrue($this->owner->fresh()->is_platform_admin);

        $this->actingAs($this->employee)->putJson('/api/me', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => $this->employee->email, 'is_platform_admin' => true,
        ])->assertOk();
        $this->assertFalse($this->employee->fresh()->is_platform_admin);
    }

    public function test_a_person_can_hold_roles_in_several_gyms(): void
    {
        $other = Gym::factory()->create();
        $other->addPerson($this->owner, GymPersonType::Member);

        $this->assertCount(2, $this->actingAs($this->owner)->getJson('/api/me')->json('data.gyms'));
        $this->assertTrue($this->owner->hasGymRole($other, GymPersonType::Member));
        $this->assertFalse($this->owner->hasGymRole($other, GymPersonType::Owner));
    }

    public function test_owner_creates_edits_and_deletes_a_role(): void
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('roles'), [
            'name' => 'Head Coach',
            'description' => 'Runs classes.',
            'permissions' => ['training.sessions.view', 'training.sessions.manage'],
        ])->assertCreated()
            ->assertJsonPath('role.description', 'Runs classes.')
            ->assertJsonPath('role.status', 'active')
            ->assertJsonPath('role.is_system', false)
            ->json('role.id');

        $this->actingAs($this->owner)->putJson($this->url("roles/{$id}"), [
            'name' => 'Senior Coach', 'permissions' => ['training.bookings.approve'],
        ])->assertOk()->assertJsonPath('role.name', 'Senior Coach');

        $this->actingAs($this->owner)->postJson($this->url('roles'), ['name' => 'Senior Coach', 'permissions' => []])
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$id}"))->assertOk();
        $this->assertSame(0, GymRole::withoutGlobalScope('gym')->count());
    }

    public function test_catalog_lists_active_permissions_and_expanded_templates(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/permissions/catalog')->assertOk();
        $this->assertCount(count(PermissionCatalog::all()), $response->json('permissions'));
        $this->assertSame('Coach', $response->json('templates.coach.name'));
        $this->assertContains('training.sessions.manage', $response->json('templates.coach.permissions'));
    }

    public function test_access_comes_from_roles_and_follows_role_changes(): void
    {
        $cashier = $this->role(['finance' => 'view'], name: 'Cashier');
        $desk = $this->role(['finance' => 'manage', 'members' => 'view'], name: 'Desk');

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$cashier->id, $desk->id],
        ])->assertOk()->assertJsonCount(2, 'roles');

        // Permissions combine across roles without inferring lower actions.
        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.manage'));

        // Editing a role changes access for everyone holding it.
        $this->actingAs($this->owner)->putJson($this->url("roles/{$desk->id}"), [
            'name' => 'Desk', 'permissions' => PermissionCatalog::expandLevels(['members' => 'view']),
        ])->assertOk();
        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.view'));
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.manage'));

        // Taking a role away removes its access; the emptied role can then be deleted.
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$desk->id],
        ])->assertOk();
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$cashier->id}"))->assertOk();
    }

    public function test_me_lists_permission_strings_per_gym(): void
    {
        $this->give($this->employee, $this->role(['finance.payments.view'], name: 'Cashier'));
        $this->actingAs($this->employee)->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.gyms.0.permissions', ['finance.payments.view']);
        $this->assertCount(67, $this->actingAs($this->owner)->getJson('/api/me')->json('data.gyms.0.permissions'));
    }

    public function test_a_role_still_given_to_employees_cannot_be_deleted(): void
    {
        $inUse = $this->role(['finance' => 'view'], name: 'Cashier');
        $unused = $this->role(['members' => 'view'], name: 'Unused');
        $this->give($this->employee, $inUse);

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$inUse->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This role is still given to employees. Take it away from them before deleting it.');

        $this->assertSame(1, $inUse->users()->count());

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$unused->id}"))->assertOk();
        $this->assertNull(GymRole::withoutGlobalScope('gym')->find($unused->id));
    }

    public function test_employee_without_hr_access_is_refused_on_the_server(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('roles'))->assertForbidden();
        $this->actingAs($this->employee)->postJson($this->url('roles'), ['name' => 'X'])->assertForbidden();
    }

    public function test_nobody_handles_a_role_above_their_own_access_or_changes_their_own_roles(): void
    {
        $this->give($this->employee, $this->role(['hr' => 'manage', 'finance' => 'view'], name: 'HR Officer'));
        $colleague = User::factory()->create();
        $this->gym->addPerson($colleague, GymPersonType::Employee);
        $big = $this->role(['finance' => 'approve'], name: 'Finance Head');
        $small = $this->role(['finance' => 'view'], name: 'Cashier');

        $hr = $this->actingAs($this->employee);
        $hr->postJson($this->url('roles'), ['name' => 'Too much', 'permissions' => ['payroll.runs.view']])->assertForbidden();
        $hr->postJson($this->url('roles'), ['name' => 'Ok', 'permissions' => ['finance.payments.view']])->assertCreated();
        $hr->putJson($this->url("roles/{$big->id}"), ['name' => 'Finance Head', 'permissions' => ['finance.payments.view']])->assertForbidden();
        $hr->deleteJson($this->url("roles/{$big->id}"))->assertForbidden();
        $hr->putJson($this->url("staff/{$colleague->id}/roles"), ['role_ids' => [$big->id]])->assertForbidden();
        $hr->putJson($this->url("staff/{$colleague->id}/roles"), ['role_ids' => [$small->id]])->assertOk();
        $hr->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => []])->assertForbidden();
    }

    public function test_one_gyms_roles_are_invisible_and_unusable_in_another(): void
    {
        $otherGym = Gym::factory()->create();
        $foreign = $this->role(['finance' => 'view'], $otherGym, 'Foreign');
        $this->role(['finance' => 'view'], name: 'Mine');

        $this->actingAs($this->owner)->getJson($this->url('roles'))
            ->assertOk()->assertJsonCount(1, 'roles')->assertJsonPath('roles.0.name', 'Mine');
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => [$foreign->id]])
            ->assertStatus(422);
        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$foreign->id}"))->assertNotFound();
    }

    public function test_people_of_one_gym_cannot_reach_another_gym(): void
    {
        $otherGym = Gym::factory()->create();
        $otherEmployee = User::factory()->create();
        $otherGym->addPerson($otherEmployee, GymPersonType::Employee);

        $this->actingAs($this->owner)->getJson($this->url("staff/{$otherEmployee->id}/roles", $otherGym))->assertNotFound();
        $this->actingAs($this->owner)->getJson($this->url("staff/{$otherEmployee->id}/roles"))->assertNotFound();
    }

    public function test_platform_admin_cannot_open_gym_records(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($admin)->getJson($this->url('roles'))->assertNotFound();
    }

    public function test_the_shared_gym_rule_fails_closed_and_blocks_cross_gym_writes(): void
    {
        $otherGym = Gym::factory()->create();
        $this->role(['finance' => 'view']);

        $this->assertSame(0, GymRole::count()); // no current gym: nothing

        app(CurrentGym::class)->set($otherGym);
        $this->assertSame(0, GymRole::count()); // another gym: nothing

        $this->expectException(LogicException::class);
        $role = new GymRole(['name' => 'Sneaky']);
        $role->gym_id = $this->gym->id;
        $role->save();
    }
}
