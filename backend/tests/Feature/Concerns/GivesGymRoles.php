<?php

namespace Tests\Feature\Concerns;

use App\Models\GymRole;
use App\Support\PermissionCatalog;

trait GivesGymRoles
{
    protected function makeRole(array $access, string $name = 'Role', array $attributes = []): GymRole
    {
        $role = GymRole::create(['name' => $name, ...$attributes]);
        $role->syncPermissionStrings(self::accessToPermissions($access));

        return $role;
    }

    protected static function accessToPermissions(array $access): array
    {
        $strings = [];
        $levels = [];
        foreach ($access as $key => $value) {
            is_int($key) ? $strings[] = $value : $levels[$key] = $value;
        }

        return array_values(array_unique([...$strings, ...PermissionCatalog::expandLevels($levels)]));
    }
}
