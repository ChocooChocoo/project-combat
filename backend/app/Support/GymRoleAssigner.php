<?php

namespace App\Support;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GymRoleAssigner
{
    public function replace(User $actor, Gym $gym, User $employee, array $roleIds, array $expiresAt = []): void
    {
        $roleIds = array_map('intval', $roleIds);
        DB::transaction(function () use ($actor, $gym, $employee, $roleIds, $expiresAt) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            abort_unless($employee->hasGymRole($gym, GymPersonType::Employee), 404);
            abort_if($actor->is($employee), 403, 'You cannot change your own roles.');
            abort_if($roleIds !== [] && $employee->hasGymRole($gym, GymPersonType::Owner), 422, 'Owners receive access through ownership, not employee roles.');
            $wanted = GymRole::withoutGlobalScope('gym')->where('gym_id', $gym->id)->whereKey($roleIds)->lockForUpdate()->get();
            abort_if($wanted->count() !== count($roleIds), 422, 'One or more roles do not exist in this gym.');
            $current = $employee->gymRoles($gym)->lockForUpdate()->get()->keyBy('id');
            $own = $actor->permissionsIn($gym);
            foreach ($current as $role) {
                if (! in_array($role->id, $roleIds, true)) {
                    RoleGuard::ensureCleanupWithin($own, $role->permissionStrings());
                    $role->users()->detach($employee->id);
                }
            }
            foreach ($wanted as $role) {
                $pivot = $role->users()->whereKey($employee->id)->first()?->pivot;
                // Omitted dates preserve existing grants, including expired history.
                $end = array_key_exists($role->id, $expiresAt)
                    ? ($expiresAt[$role->id] === null ? null : Carbon::parse($expiresAt[$role->id])->endOfDay()->toDateTimeString())
                    : $pivot?->expires_at;
                if (! $pivot || $end !== $pivot->expires_at) {
                    RoleGuard::ensureWithin($own, $role->permissionStrings());
                    abort_unless($role->status === 'active', 422, 'Inactive roles cannot be newly assigned or extended.');
                    if ($pivot) {
                        $role->users()->updateExistingPivot($employee->id, ['expires_at' => $end, 'granted_by' => $actor->id]);
                    } else {
                        $role->users()->attach($employee->id, ['expires_at' => $end, 'granted_by' => $actor->id]);
                    }
                }
            }
        });
    }
}
