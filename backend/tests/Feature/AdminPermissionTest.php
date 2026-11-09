<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_only_admin_can_manage_catalog(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/permissions')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/admin/permissions')->assertOk()->assertJsonCount(67, 'permissions');
    }

    public function test_custom_permission_crud_and_validation(): void
    {
        $payload = ['module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust'];
        $id = $this->actingAs($this->admin)->postJson('/api/admin/permissions', $payload)->assertCreated()
            ->assertJsonPath('permission.permission_string', 'inventory.stock.adjust')->assertJsonPath('permission.name', 'Adjust stock')
            ->assertJsonPath('permission.is_system', false)->json('permission.id');
        $this->postJson('/api/admin/permissions', $payload)->assertJsonValidationErrors('action');
        $this->postJson('/api/admin/permissions', [...$payload, 'module' => 'unknown'])->assertJsonValidationErrors('module');
        $this->postJson('/api/admin/permissions', [...$payload, 'action' => str_repeat('a', 31)])->assertJsonValidationErrors('action');
        $this->postJson('/api/admin/permissions', [...$payload, 'resource' => str_repeat('r', 60)])->assertCreated();
        $this->putJson("/api/admin/permissions/{$id}", ['name' => 'Fix counts'])->assertOk()->assertJsonPath('permission.name', 'Fix counts');
        $this->deleteJson("/api/admin/permissions/{$id}")->assertOk();
        $this->assertNull(Permission::find($id));
    }

    public function test_global_usage_count_and_delete_guard_work_without_current_gym(): void
    {
        $custom = Permission::create(['module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust',
            'permission_string' => 'inventory.stock.adjust', 'name' => 'Adjust stock']);
        app(CurrentGym::class)->set(Gym::factory()->create());
        $this->makeRole(['inventory.stock.adjust']);
        app(CurrentGym::class)->set(Gym::factory()->create());
        $this->makeRole(['inventory.stock.adjust']);
        $this->app->forgetScopedInstances();
        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions')->assertOk();
        $entry = collect($response->json('permissions'))->firstWhere('id', $custom->id);
        $this->assertSame(2, $entry['roles_count']);
        $this->deleteJson("/api/admin/permissions/{$custom->id}")->assertStatus(422);
        $system = Permission::where('permission_string', 'finance.payments.view')->firstOrFail();
        $this->deleteJson("/api/admin/permissions/{$system->id}")->assertStatus(422);
    }

    public function test_disable_and_sync_keep_owners_denied_without_losing_custom_rows(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $gym->addPerson($owner, GymPersonType::Owner);
        $permission = Permission::where('permission_string', 'finance.payments.view')->firstOrFail();
        $this->actingAs($this->admin)->postJson("/api/admin/permissions/{$permission->id}/toggle-status")->assertOk();
        $this->assertFalse($owner->hasPermission($gym, 'finance.payments.view'));
        Permission::where('permission_string', 'finance.payments.manage')->delete();
        $this->artisan('app:sync-permissions')->assertSuccessful();
        $this->assertSame('inactive', $permission->fresh()->status);
        $this->assertDatabaseHas('permissions', ['permission_string' => 'finance.payments.manage']);
    }
}
