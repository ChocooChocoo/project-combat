<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_05_110000_move_gym_roles_to_permission_catalog.php');
    }

    public function test_legacy_conversion_and_safe_round_trip_preserve_exact_access(): void
    {
        $migration = $this->migration();
        $migration->down();
        $gym = Gym::factory()->create();
        $levels = ['training' => 'manage', 'finance' => 'view'];
        $id = DB::table('gym_roles')->insertGetId(['gym_id' => $gym->id, 'name' => 'Legacy', 'permissions' => json_encode($levels)]);
        $user = \App\Models\User::factory()->create();
        $grant = DB::table('gym_role_user')->insertGetId(['gym_role_id' => $id, 'user_id' => $user->id, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $before = (array) DB::table('gym_role_user')->where('id', $grant)->first();
        $migration->up();
        $keys = DB::table('gym_role_permission')->join('permissions', 'permission_id', '=', 'permissions.id')->where('gym_role_id', $id)->pluck('permission_string')->all();
        $this->assertEqualsCanonicalizing(PermissionCatalog::expandLevels($levels), $keys);
        $this->assertDatabaseHas('gym_role_user', [...$before, 'expires_at' => null, 'granted_by' => null]);
        $migration->down();
        $this->assertSame($levels, json_decode(DB::table('gym_roles')->where('id', $id)->value('permissions'), true));
        $this->assertSame($before, (array) DB::table('gym_role_user')->where('id', $grant)->first());
        $migration->up();
    }

    public function test_malformed_legacy_data_is_rejected_before_schema_changes(): void
    {
        $migration = $this->migration();
        $migration->down();
        $gym = Gym::factory()->create();
        $id = DB::table('gym_roles')->insertGetId(['gym_id' => $gym->id, 'name' => 'Broken', 'permissions' => '{"training":"boss"}']);
        try {
            $migration->up();
            $this->fail('Malformed permissions accepted.');
        } catch (\RuntimeException) {
            $this->assertFalse(Schema::hasTable('permissions'));
            $this->assertSame('{"training":"boss"}', DB::table('gym_roles')->where('id', $id)->value('permissions'));
        }
        DB::table('gym_roles')->where('id', $id)->update(['permissions' => '{}']);
        $migration->up();
    }

    public function test_disabled_permission_downgrade_is_refused_without_schema_changes(): void
    {
        DB::table('permissions')->where('permission_string', 'finance.payments.view')->update(['status' => 'inactive']);
        try {
            $this->migration()->down();
            $this->fail('Disabled permission downgrade accepted.');
        } catch (\RuntimeException) {
            $this->assertFalse(Schema::hasColumn('gym_roles', 'permissions'));
            $this->assertDatabaseHas('permissions', ['permission_string' => 'finance.payments.view', 'status' => 'inactive']);
        }
    }

    public function test_inactive_role_and_dated_grant_each_refuse_downgrade(): void
    {
        $gym = Gym::factory()->create();
        $user = \App\Models\User::factory()->create();
        $id = DB::table('gym_roles')->insertGetId(['gym_id' => $gym->id, 'name' => 'Off', 'status' => 'inactive']);
        foreach (['status', 'expiry'] as $mode) {
            if ($mode === 'expiry') {
                DB::table('gym_roles')->where('id', $id)->update(['status' => 'active']);
                DB::table('gym_role_user')->insert(['gym_role_id' => $id, 'user_id' => $user->id, 'expires_at' => now()->subDay()]);
            }
            try {
                $this->migration()->down();
                $this->fail('Unrepresentable role state accepted.');
            } catch (\RuntimeException) {
                $this->assertFalse(Schema::hasColumn('gym_roles', 'permissions'));
                $this->assertDatabaseHas('gym_roles', ['id' => $id]);
            }
        }
    }

    public function test_lossy_downgrade_is_rejected_before_schema_changes(): void
    {
        $gym = Gym::factory()->create();
        $id = DB::table('gym_roles')->insertGetId(['gym_id' => $gym->id, 'name' => 'Exact']);
        $permission = DB::table('permissions')->where('permission_string', 'training.bookings.approve')->value('id');
        DB::table('gym_role_permission')->insert(['gym_role_id' => $id, 'permission_id' => $permission]);
        try {
            $this->migration()->down();
            $this->fail('Lossy downgrade accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unsafe RBAC downgrade', $exception->getMessage());
            $this->assertFalse(Schema::hasColumn('gym_roles', 'permissions'));
            $this->assertDatabaseHas('gym_role_permission', ['gym_role_id' => $id, 'permission_id' => $permission]);
        }
    }
}
