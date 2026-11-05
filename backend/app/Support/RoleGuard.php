<?php

namespace App\Support;

use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

final class RoleGuard
{
    public static function ensureWithin(Collection $own, array $permissions): void
    {
        abort_if(array_diff($permissions, $own->all()) !== [], 403, 'You cannot handle a role with access you do not have.');
    }

    /** Disabled permissions may be removed, never newly added or reactivated. */
    public static function ensureCleanupWithin(Collection $own, array $permissions): void
    {
        self::ensureWithin($own, Permission::active()->whereIn('permission_string', $permissions)->pluck('permission_string')->all());
    }

    public static function ensureNotHeld(User $actor, GymRole $role): void
    {
        abort_if($role->users()->whereKey($actor->id)->exists(), 403, 'Another authorized person must change a role you hold.');
    }
}
