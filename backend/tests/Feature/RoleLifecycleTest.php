<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

class RoleLifecycleTest extends TestCase
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
        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    public function test_toggle_preserves_grants_and_disabled_cleanup_does_not_allow_reactivation(): void
    {
        $role = $this->makeRole(['finance.payments.view']);
        $role->users()->attach($this->employee->id);
        Permission::where('permission_string', 'finance.payments.view')->update(['status' => 'inactive']);
        $this->actingAs($this->owner)->postJson($this->url("roles/{$role->id}/toggle-status"))->assertOk()->assertJsonPath('role.status', 'inactive');
        $this->assertTrue($role->users()->whereKey($this->employee->id)->exists());
        $this->actingAs($this->owner)->postJson($this->url("roles/{$role->id}/toggle-status"))->assertForbidden();
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));
    }

    public function test_self_held_role_toggle_is_blocked_even_when_expired(): void
    {
        $this->makeRole(['hr.roles.manage'], 'HR')->users()->attach($this->employee->id);
        $role = $this->makeRole([], 'Expired');
        $role->users()->attach($this->employee->id, ['expires_at' => now()->subDay()]);
        $this->actingAs($this->employee)->postJson($this->url("roles/{$role->id}/toggle-status"))->assertForbidden();
    }

    public function test_dates_are_end_of_day_and_expired_grants_can_be_preserved_without_resubmission(): void
    {
        $role = $this->makeRole(['finance.payments.view']);
        $until = now()->addDays(3)->toDateString();
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$role->id], 'expires_at' => [$role->id => $until],
        ])->assertOk()->assertJsonPath('roles.0.expires_at', $until.' 23:59:59');
        $role->users()->updateExistingPivot($this->employee->id, ['expires_at' => now()->subDay()]);
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => [$role->id]])->assertOk();
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$role->id], 'expires_at' => [$role->id => now()->subDays(2)->toDateString()],
        ])->assertJsonValidationErrors("expires_at.{$role->id}");
        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$role->id}"))->assertStatus(422);
    }

    public function test_starter_seed_is_idempotent_after_rename_and_does_not_overwrite_customization(): void
    {
        SystemRoles::seed($this->gym);
        $role = GymRole::withoutGlobalScope('gym')->where('template_key', 'coach')->firstOrFail();
        $role->update(['name' => 'Renamed coach']);
        $role->syncPermissionStrings([]);
        SystemRoles::seed($this->gym);
        $this->assertSame(4, GymRole::withoutGlobalScope('gym')->count());
        $this->assertSame([], $role->permissionStrings());
        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$role->id}"))->assertStatus(422);
    }

    public function test_starter_seed_rolls_back_when_permission_linking_fails(): void
    {
        \Illuminate\Support\Facades\DB::unprepared("CREATE TRIGGER reject_starter_permission BEFORE INSERT ON gym_role_permission BEGIN SELECT RAISE(ABORT, 'forced seed failure'); END");
        try {
            SystemRoles::seed($this->gym);
            $this->fail('Expected seed failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('forced seed failure', $exception->getMessage());
        }
        $this->assertSame(0, GymRole::withoutGlobalScope('gym')->where('is_system', true)->count());
    }

    public function test_starter_seed_skips_case_insensitive_name_collision(): void
    {
        $custom = $this->makeRole([], 'cOaCh');
        SystemRoles::seed($this->gym);
        SystemRoles::seed($this->gym);
        $this->assertSame(4, GymRole::withoutGlobalScope('gym')->count());
        $this->assertFalse($custom->fresh()->is_system);
    }
}
