<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\GymPersonType;
use App\Enums\Module;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// is_platform_admin and password_change_required are deliberately not fillable.
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'password_change_required' => 'boolean',
        ];
    }

    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)->withPivot('role')->withTimestamps();
    }

    public function hasGymRole(Gym|int $gym, GymPersonType ...$roles): bool
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;
        $values = array_map(fn (GymPersonType $r) => $r->value, $roles ?: GymPersonType::cases());

        return $this->gyms()->where('gyms.id', $gymId)->wherePivotIn('role', $values)->exists();
    }

    /** Access level in one module of one gym. Owners hold every module in full (spec, page 7). */
    public function accessLevel(Gym|int $gym, Module $module): ?AccessLevel
    {
        if ($this->hasGymRole($gym, GymPersonType::Owner)) {
            return AccessLevel::Approve;
        }

        if (! $this->hasGymRole($gym, GymPersonType::Employee)) {
            return null;
        }

        // The highest level any of this person's roles in that gym gives (R-08).
        return $this->gymRoles($gym)->get()
            ->map(fn (GymRole $role) => $role->levelFor($module))
            ->filter()
            ->sortByDesc(fn (AccessLevel $level) => $level->rank())
            ->first();
    }

    /** This person's owner-made roles in one gym. Explicit gym filter, so it works outside a request too. */
    public function gymRoles(Gym|int $gym)
    {
        return GymRole::withoutGlobalScope('gym')
            ->where('gym_id', $gym instanceof Gym ? $gym->id : $gym)
            ->whereHas('users', fn ($q) => $q->whereKey($this->id));
    }

    public function canInGym(Gym|int $gym, Module $module, AccessLevel $needed): bool
    {
        return (bool) $this->accessLevel($gym, $module)?->covers($needed);
    }
}
