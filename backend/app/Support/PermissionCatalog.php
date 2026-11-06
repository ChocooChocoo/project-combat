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

    /** @param array<string, string|list<string>|null> $template
     *  @return list<string>
     */
    public static function expandTemplate(array $template): array
    {
        $levels = [];
        $exact = [];

        foreach ($template as $module => $value) {
            if (is_array($value)) {
                self::assertKnown(...$value);
                $exact = [...$exact, ...$value];
            } else {
                $levels[$module] = $value;
            }
        }

        return array_values(array_unique([...$exact, ...self::expandLevels($levels)]));
    }

    /**
     * Maps one retired placeholder key to the exact keys that replace it.
     * A level covers its own action and every lower action only.
     *
     * @return list<string>
     */
    public static function replacementFor(string $permission): array
    {
        $parts = explode('.', $permission);

        if (count($parts) !== 3 || $parts[1] !== 'general'
            || ! in_array($parts[0], ['payroll', 'customers', 'inventory', 'procurement'], true)) {
            return [];
        }

        $limit = AccessLevel::tryFrom($parts[2]);

        if (! $limit) {
            return [];
        }

        $replacement = [];

        foreach (config("permissions.catalog.{$parts[0]}", []) as $resource => $actions) {
            foreach ($actions as $action) {
                $needed = AccessLevel::tryFrom($action);
                if ($needed && $limit->covers($needed)) {
                    $replacement[] = "{$parts[0]}.{$resource}.{$action}";
                }
            }
        }

        return $replacement;
    }

    public static function label(string $resource, string $action): string
    {
        return ucfirst($action).' '.str_replace('_', ' ', $resource);
    }

    /**
     * Adds missing system keys and removes system keys that left the catalog.
     * Disabled permissions stay disabled. Custom rows are never touched.
     */
    public static function sync(): void
    {
        $all = self::all();

        if ($all === []) {
            return;
        }

        foreach ($all as $permission) {
            [$module, $resource, $action] = explode('.', $permission);
            DB::table('permissions')->insertOrIgnore([
                'module' => $module, 'resource' => $resource, 'action' => $action,
                'permission_string' => $permission, 'name' => self::label($resource, $action),
                'status' => 'active', 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('permissions')->where('permission_string', $permission)->update(['is_system' => true]);
        }

        DB::table('permissions')
            ->where('is_system', true)
            ->whereNotIn('permission_string', $all)
            ->delete();
    }
}
