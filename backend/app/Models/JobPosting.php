<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-138: an opening a gym posts. The role, when set, is given to the person hired. */
#[Fillable(['title', 'gym_role_id', 'description', 'requirements', 'status'])]
class JobPosting extends Model
{
    use BelongsToGym;

    public function role(): BelongsTo
    {
        return $this->belongsTo(GymRole::class, 'gym_role_id')->withoutGlobalScope('gym');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
