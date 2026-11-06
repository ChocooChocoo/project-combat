<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the four business placeholders (payroll, customers, inventory,
 * procurement) with the exact resources each module now uses.
 *
 * Existing role grants keep the same reach: a grant of "manage" becomes every
 * new key at view or manage level, never approve.
 */
return new class extends Migration
{
    private const PLACEHOLDERS = ['payroll', 'customers', 'inventory', 'procurement'];

    public function up(): void
    {
        // Capture grants before sync() removes the retired rows.
        $grants = [];
        $rows = DB::table('gym_role_permission')
            ->join('permissions', 'permissions.id', '=', 'gym_role_permission.permission_id')
            ->get(['gym_role_permission.gym_role_id', 'permissions.permission_string']);

        foreach ($rows as $row) {
            $grants[$row->gym_role_id][] = $row->permission_string;
        }

        PermissionCatalog::sync();

        $ids = DB::table('permissions')->pluck('id', 'permission_string');
        $now = now();

        foreach ($grants as $roleId => $strings) {
            foreach ($strings as $string) {
                foreach (PermissionCatalog::replacementFor($string) as $replacement) {
                    if (! isset($ids[$replacement])) {
                        throw new RuntimeException("Replacement permission {$replacement} is missing from the catalog.");
                    }

                    DB::table('gym_role_permission')->insertOrIgnore([
                        'gym_role_id' => $roleId,
                        'permission_id' => $ids[$replacement],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Restores the retired placeholder rows. New grants stay in place: removing
     * them would silently take access away. A lossless downgrade needs a backup.
     */
    public function down(): void
    {
        $now = now();

        foreach (self::PLACEHOLDERS as $module) {
            foreach (['view', 'manage', 'approve'] as $action) {
                DB::table('permissions')->insertOrIgnore([
                    'module' => $module,
                    'resource' => 'general',
                    'action' => $action,
                    'permission_string' => implode('.', [$module, 'general', $action]),
                    'name' => PermissionCatalog::label('general', $action),
                    'status' => 'active',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
