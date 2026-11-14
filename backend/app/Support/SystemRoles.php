<?php

namespace App\Support;

use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SystemRoles
{
    /**
     * The current gym's active starter role for a template. seed() skips a template when a role with the same
     * name already exists, so that role has no template_key and is matched by name instead.
     */
    public static function role(string $key): ?GymRole
    {
        $name = config("access.names.{$key}", Str::headline($key));

        return GymRole::where('status', 'active')
            ->where(fn ($query) => $query->where('template_key', $key)->orWhereRaw('LOWER(name) = ?', [Str::lower($name)]))
            ->orderByRaw('template_key IS NULL')
            ->first();
    }

    public static function seed(Gym $gym): void
    {
        DB::transaction(function () use ($gym) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            PermissionCatalog::sync();
            foreach (config('access.templates') as $key => $levels) {
                $name = config("access.names.{$key}", Str::headline($key));
                $roles = GymRole::withoutGlobalScope('gym')->where('gym_id', $gym->id);
                if ((clone $roles)->where('template_key', $key)->exists()
                    || (clone $roles)->whereRaw('LOWER(name) = ?', [Str::lower($name)])->exists()) {
                    continue;
                }
                $role = GymRole::withoutEvents(fn () => GymRole::withoutGlobalScope('gym')->forceCreate([
                    'gym_id' => $gym->id, 'name' => $name, 'description' => 'Starter role. Change it to fit your gym.',
                    'status' => 'active', 'is_system' => true, 'template_key' => $key,
                ]));
                $keys = Permission::active()->whereIn('permission_string', PermissionCatalog::expandTemplate($levels))->pluck('permission_string')->all();
                $role->syncPermissionStrings($keys);
            }
        });
    }
}
