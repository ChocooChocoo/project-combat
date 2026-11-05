<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

class RoleSafetyTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private Gym $gym;
    private User $owner;
    private User $actor;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create();
        $this->actor = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->actor, GymPersonType::Employee);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    public function test_explicit_staff_role_ids_require_roles_manage_even_when_empty(): void
    {
        $this->makeRole(['hr.staff.manage'])->users()->attach($this->actor->id);
        foreach ([[], [999]] as $ids) {
            $this->actingAs($this->actor)->postJson($this->url('staff'), [
                'email' => $this->employee->email, 'role_ids' => $ids,
            ])->assertForbidden();
        }
        $this->actingAs($this->actor)->postJson($this->url('staff'), ['email' => $this->employee->email])->assertCreated();
    }

    public function test_staff_removal_cannot_revoke_roles_above_actor_access(): void
    {
        $this->makeRole(['hr' => 'manage'], 'HR')->users()->attach($this->actor->id);
        $role = $this->makeRole(['finance.payments.manage'], 'Finance');
        $role->users()->attach($this->employee->id);
        $this->actingAs($this->actor)->deleteJson($this->url("staff/{$this->employee->id}"))->assertForbidden();
        $this->assertTrue($this->employee->hasGymRole($this->gym, GymPersonType::Employee));
        $this->assertTrue($role->users()->whereKey($this->employee->id)->exists());
    }

    public function test_self_held_role_cannot_be_edited_even_if_grant_expired(): void
    {
        $this->makeRole(['hr' => 'manage'], 'HR')->users()->attach($this->actor->id);
        $role = $this->makeRole([], 'Expired');
        $role->users()->attach($this->actor->id, ['expires_at' => now()->subDay()]);
        $this->actingAs($this->actor)->putJson($this->url("roles/{$role->id}"), ['name' => 'Changed', 'permissions' => []])->assertForbidden();
        $this->actingAs($this->actor)->putJson($this->url("staff/{$this->actor->id}/roles"), ['role_ids' => []])->assertForbidden();
    }

    public function test_owner_can_preserve_or_remove_disabled_keys_but_cannot_add_them(): void
    {
        $role = $this->makeRole(['finance.payments.view'], 'Cashier');
        Permission::whereIn('permission_string', ['finance.payments.view', 'finance.payments.manage'])->update(['status' => 'inactive']);
        $this->actingAs($this->owner)->putJson($this->url("roles/{$role->id}"), [
            'name' => 'Renamed', 'permissions' => ['finance.payments.view'],
        ])->assertOk()->assertJsonPath('role.inactive_permissions', ['finance.payments.view']);
        $this->actingAs($this->owner)->putJson($this->url("roles/{$role->id}"), [
            'name' => 'Renamed', 'permissions' => ['finance.payments.view', 'finance.payments.manage'],
        ])->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url('roles'), [
            'name' => 'Dormant smuggle', 'permissions' => ['finance.payments.view'],
        ])->assertForbidden();
        $this->actingAs($this->owner)->putJson($this->url("roles/{$role->id}"), ['name' => 'Cleaned', 'permissions' => []])->assertOk();
        $this->assertSame([], $role->permissionStrings());
    }

    public function test_owner_cannot_receive_employee_roles_even_with_a_legacy_employee_membership(): void
    {
        $this->gym->addPerson($this->owner, GymPersonType::Employee);
        $this->makeRole(['hr.roles.manage', 'finance.payments.view'], 'Role editor')->users()->attach($this->actor->id);
        $role = $this->makeRole(['finance.payments.view'], 'Cashier');
        $this->actingAs($this->actor)->putJson($this->url("staff/{$this->owner->id}/roles"), ['role_ids' => [$role->id]])->assertStatus(422);
        $this->assertFalse($role->users()->whereKey($this->owner->id)->exists());
    }

    public function test_role_names_are_case_insensitively_unique_within_one_gym(): void
    {
        $this->makeRole([], 'Coach');
        $this->actingAs($this->owner)->postJson($this->url('roles'), ['name' => 'cOaCh', 'permissions' => []])->assertJsonValidationErrors('name');
    }

    public function test_dormant_grants_survive_unrelated_assignment_edits_but_cannot_be_newly_assigned(): void
    {
        $role = $this->makeRole(['finance.payments.view'], 'Dormant');
        $role->users()->attach($this->employee->id);
        Permission::where('permission_string', 'finance.payments.view')->update(['status' => 'inactive']);
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => [$role->id]])->assertOk();
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->actor->id}/roles"), ['role_ids' => [$role->id]])->assertForbidden();
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => []])->assertOk();
    }
}
