<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Frozen legacy mapping: future catalog changes must not change migrated access.
    private const RESOURCES = [
        'hr' => ['staff' => 2, 'roles' => 2, 'schedules' => 2, 'leave' => 2, 'holidays' => 0],
        'gym_setup' => ['settings' => 2, 'rooms' => 2, 'services' => 2],
        'training' => ['sessions' => 2, 'bookings' => 3, 'attendance' => 2, 'check_ins' => 0, 'athletes' => 2],
        'members' => ['plans' => 2, 'memberships' => 3],
        'health_records' => ['records' => 1], 'finance' => ['payments' => 2],
        'payroll' => ['general' => 3], 'customers' => ['general' => 3],
        'inventory' => ['general' => 3], 'procurement' => ['general' => 3],
        'announcements' => ['general' => 3], 'decision_support' => ['general' => 3],
    ];

    private function expand(array $levels): array
    {
        $keys = [];
        foreach ($levels as $module => $level) {
            if (! isset(self::RESOURCES[$module]) || ! in_array($level, [null, 'view', 'manage', 'approve'], true)) {
                throw new RuntimeException('Malformed legacy role permissions. Restore or repair from backup before migrating.');
            }
            $rank = array_search($level, [null, 'view', 'manage', 'approve'], true);
            foreach (self::RESOURCES[$module] as $resource => $max) {
                foreach (['view' => 1, 'manage' => 2, 'approve' => 3] as $action => $needed) {
                    if ($needed <= $rank && ($max === 0 ? $needed === 2 : $needed <= $max)) {
                        $keys[] = "{$module}.{$resource}.{$action}";
                    }
                }
            }
        }

        return $keys;
    }

    /** The 51 keys this migration froze. It must not read a later catalog. */
    private function frozenPermissions(): array
    {
        $keys = [];
        foreach (self::RESOURCES as $module => $resources) {
            foreach ($resources as $resource => $max) {
                foreach (['view' => 1, 'manage' => 2, 'approve' => 3] as $action => $needed) {
                    if ($max === 0 ? $needed === 2 : $needed <= $max) {
                        $keys[] = "{$module}.{$resource}.{$action}";
                    }
                }
            }
        }

        return $keys;
    }

    public function up(): void
    {
        $converted = [];
        foreach (DB::table('gym_roles')->get(['id', 'permissions']) as $role) {
            $levels = json_decode($role->permissions, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($levels)) {
                throw new RuntimeException('Legacy role permissions must be a module map.');
            }
            $converted[$role->id] = $this->expand($levels);
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40)->index();
            $table->string('resource', 60);
            $table->string('action', 30);
            $table->string('permission_string', 150)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 10)->default('active');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('gym_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gym_role_id', 'permission_id']);
        });
        Schema::table('gym_roles', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('status', 10)->default('active');
            $table->boolean('is_system')->default(false);
            $table->string('template_key', 40)->nullable();
            $table->unique(['gym_id', 'template_key']);
        });
        Schema::table('gym_role_user', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
        });
        $now = now();
        foreach ($this->frozenPermissions() as $permission) {
            [$module, $resource, $action] = explode('.', $permission);
            DB::table('permissions')->insertOrIgnore([
                'module' => $module, 'resource' => $resource, 'action' => $action,
                'permission_string' => $permission, 'name' => PermissionCatalog::label($resource, $action),
                'status' => 'active', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $ids = DB::table('permissions')->pluck('id', 'permission_string');
        foreach ($converted as $roleId => $keys) {
            foreach ($keys as $key) {
                if (! isset($ids[$key])) {
                    throw new RuntimeException("Legacy permission {$key} is missing from the catalog.");
                }
                DB::table('gym_role_permission')->insert([
                    'gym_role_id' => $roleId, 'permission_id' => $ids[$key],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        Schema::table('gym_roles', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }

    public function down(): void
    {
        if (DB::table('gym_roles')->where('status', '!=', 'active')->exists()
            || DB::table('permissions')->where('status', '!=', 'active')->exists()
            || DB::table('gym_role_user')->whereNotNull('expires_at')->exists()) {
            throw new RuntimeException('Unsafe RBAC downgrade: disabled roles, permissions or dated grants cannot be represented. Restore a verified pre-migration backup instead.');
        }
        $converted = [];
        foreach (DB::table('gym_roles')->pluck('id') as $id) {
            $keys = DB::table('gym_role_permission')->join('permissions', 'permissions.id', '=', 'permission_id')
                ->where('gym_role_id', $id)->pluck('permission_string')->all();
            $levels = [];
            foreach (self::RESOURCES as $module => $resources) {
                $held = array_values(array_filter($keys, fn ($key) => str_starts_with($key, $module.'.')));
                if ($held === []) {
                    continue;
                }
                foreach (['view', 'manage', 'approve'] as $level) {
                    $expected = $this->expand([$module => $level]);
                    if (array_diff($held, $expected) === [] && array_diff($expected, $held) === []) {
                        $levels[$module] = $level;
                        break;
                    }
                }
            }
            $expected = $this->expand($levels);
            if (array_diff($keys, $expected) !== [] || array_diff($expected, $keys) !== []) {
                throw new RuntimeException('Unsafe RBAC downgrade: exact role permissions cannot be represented by module levels. Restore a verified pre-migration backup instead.');
            }
            $converted[$id] = $levels;
        }
        Schema::table('gym_roles', fn (Blueprint $table) => $table->json('permissions')->nullable());
        foreach ($converted as $id => $levels) {
            DB::table('gym_roles')->where('id', $id)->update(['permissions' => json_encode($levels, JSON_THROW_ON_ERROR)]);
        }
        Schema::table('gym_role_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn('expires_at');
        });
        Schema::table('gym_roles', function (Blueprint $table) {
            $table->dropUnique(['gym_id', 'template_key']);
            $table->dropColumn(['description', 'status', 'is_system', 'template_key']);
        });
        Schema::drop('gym_role_permission');
        Schema::drop('permissions');
    }
};
