<?php

namespace App\Support;

use App\Enums\AccessLevel;
use App\Enums\GymPersonType;
use App\Enums\Module;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;

/**
 * Replaces an employee's roles in one gym. Both the roles given and the roles
 * taken away must be within the editor's own access. Owners cannot be assigned
 * here: ownership is a gym membership, not an owner-made role (R-08).
 */
class GymRoleAssigner
{
    /** @param array<int, int> $roleIds */
    public function replace(User $actor, Gym $gym, User $employee, array $roleIds): void
    {
        abort_unless($employee->hasGymRole($gym, GymPersonType::Employee), 404);
        abort_if($actor->is($employee), 403, 'You cannot change your own roles.');

        $wanted = GymRole::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereKey($roleIds)
            ->get();
        abort_if($wanted->count() !== count($roleIds), 422, 'One or more roles do not exist in this gym.');

        $current = $employee->gymRoles($gym)->get();

        foreach ($wanted->merge($current)->unique('id') as $role) {
            $this->ensureWithinOwnAccess($actor, $gym, $role->permissions);
        }

        $current->each(fn (GymRole $role) => $role->users()->detach($employee->id));
        $wanted->each(fn (GymRole $role) => $role->users()->attach($employee->id));
    }

    /** @param array<string, string> $permissions */
    private function ensureWithinOwnAccess(User $actor, Gym $gym, array $permissions): void
    {
        foreach ($permissions as $module => $level) {
            $own = $actor->accessLevel($gym, Module::from($module));

            if (! $own?->covers(AccessLevel::from($level))) {
                abort(403, "You cannot handle a role with {$module} access above your own.");
            }
        }
    }
}
