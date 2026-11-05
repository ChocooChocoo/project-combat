<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A gym-scoped set of exact catalog permissions. */
#[Fillable(['name', 'description', 'status'])]
class GymRole extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['expires_at', 'granted_by'])->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('gym_roles.status', 'active');
    }

    public function permissionStrings(): array
    {
        return $this->permissions()->pluck('permission_string')->all();
    }

    public function syncPermissionStrings(array $permissions): void
    {
        $ids = Permission::whereIn('permission_string', $permissions)->pluck('id', 'permission_string');
        if (array_diff($permissions, $ids->keys()->all()) !== []) {
            throw new InvalidArgumentException('Cannot sync unknown permission strings. Sync the catalog first.');
        }
        $this->permissions()->sync($ids->values());
    }

}
