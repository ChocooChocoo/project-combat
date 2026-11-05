<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['module', 'resource', 'action', 'permission_string', 'name', 'description', 'status', 'is_system'])]
class Permission extends Model
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        // Catalog usage is global, including admin requests with no current gym.
        return $this->belongsToMany(GymRole::class)->withoutGlobalScope('gym')->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('permissions.status', 'active');
    }
}
