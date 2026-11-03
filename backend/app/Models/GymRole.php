<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A role an owner creates for their gym, with one access level per module (R-08). */
#[Fillable(['name', 'permissions'])]
class GymRole extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function levelFor(Module $module): ?AccessLevel
    {
        return AccessLevel::tryFrom($this->permissions[$module->value] ?? '');
    }
}
