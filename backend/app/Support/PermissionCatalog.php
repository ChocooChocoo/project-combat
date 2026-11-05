<?php

namespace App\Support;

use App\Enums\AccessLevel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PermissionCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        $all = [];
        foreach (config('permissions.catalog') as $module => $resources) {
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $all[] = "{$module}.{$resource}.{$action}";
                }
            }
        }

        return $all;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    public static function assertKnown(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            if (! self::exists($permission)) {
                throw new InvalidArgumentException("Unknown permission: {$permission}");
            }
        }
    }

    /** @param array<string, string|null> $levels
     *  @return list<string>
     */
    public static function expandLevels(array $levels): array
    {
        $expanded = [];
        foreach ($levels as $module => $level) {
            $limit = AccessLevel::tryFrom((string) $level);
            if (! $limit || ! array_key_exists($module, config('permissions.catalog'))) {
                continue;
            }
            foreach (config("permissions.catalog.{$module}", []) as $resource => $actions) {
                foreach ($actions as $action) {
                    $needed = AccessLevel::tryFrom($action);
                    if ($needed && $limit->covers($needed)) {
                        $expanded[] = "{$module}.{$resource}.{$action}";
                    }
                }
            }
        }

        return $expanded;
    }

    public static function label(string $resource, string $action): string
    {
        return ucfirst($action).' '.str_replace('_', ' ', $resource);
    }

    /** Sync system keys without reactivating disabled permissions or removing custom rows. */
    public static function sync(): void
    {
        foreach (self::all() as $permission) {
            [$module, $resource, $action] = explode('.', $permission);
            DB::table('permissions')->insertOrIgnore([
                'module' => $module, 'resource' => $resource, 'action' => $action,
                'permission_string' => $permission, 'name' => self::label($resource, $action),
                'status' => 'active', 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('permissions')->where('permission_string', $permission)->update(['is_system' => true]);
        }
    }
}
