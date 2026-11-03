<?php

namespace App\Models;

use App\Enums\GymPersonType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Gym extends Model
{
    use HasFactory;

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function addPerson(User $user, GymPersonType $role): void
    {
        $this->people()->attach($user->id, ['role' => $role->value]);
    }
}
