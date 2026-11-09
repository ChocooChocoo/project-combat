<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\GymPersonType;
use App\Support\PermissionCatalog;
use App\Support\Subscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

// is_platform_admin and password_change_required are deliberately not fillable.
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password', 'account_type'])]
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
            'account_type' => AccountType::class,
        ];
    }

    public function isOwnerAccount(): bool
    {
        return $this->account_type === AccountType::Owner;
    }

    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)->withPivot('role')->withTimestamps();
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function gymPreference(): HasOne
    {
        return $this->hasOne(GymPreference::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScope('gym');
    }

    public function sessionBookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class)->withoutGlobalScope('gym');
    }

    /** Whether this account still has member records, so its member screens stay reachable. */
    public function hasMemberRecords(): bool
    {
        return $this->memberships()->exists() || $this->sessionBookings()->exists();
    }

    public function hasGymRole(Gym|int $gym, GymPersonType ...$roles): bool
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;
        $values = array_map(fn (GymPersonType $r) => $r->value, $roles ?: GymPersonType::cases());

        return $this->gyms()->where('gyms.id', $gymId)->wherePivotIn('role', $values)->exists();
    }

    /**
     * Active permissions from active, unexpired grants in this gym only, minus any module
     * the gym's platform plan does not include (R-106).
     */
    public function permissionsIn(Gym|int $gym): Collection
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;
        $granted = $this->grantedPermissionsIn($gymId);
        if ($granted->isEmpty()) {
            return $granted;
        }

        $model = $gym instanceof Gym ? $gym : Gym::find($gymId);
        $locked = $model ? Subscription::lockedModules($model) : [];

        return $locked === [] ? $granted
            : $granted->reject(fn (string $permission) => in_array(strtok($permission, '.'), $locked, true))->values();
    }

    /** What roles grant in this gym, before the plan's limits. */
    public function grantedPermissionsIn(int $gymId): Collection
    {
        if ($this->hasGymRole($gymId, GymPersonType::Owner)) {
            return Permission::active()->pluck('permission_string');
        }
        if (! $this->hasGymRole($gymId, GymPersonType::Employee)) {
            return collect();
        }

        return Permission::active()->whereHas('roles', fn (Builder $role) => $role
            ->where('gym_roles.gym_id', $gymId)->where('gym_roles.status', 'active')
            ->whereHas('users', fn (Builder $user) => $user->whereKey($this->id)
                ->where(fn (Builder $grant) => $grant->whereNull('gym_role_user.expires_at')
                    ->orWhere('gym_role_user.expires_at', '>', now()))))
            ->pluck('permission_string');
    }

    public function hasPermission(Gym|int $gym, string ...$anyOf): bool
    {
        PermissionCatalog::assertKnown(...$anyOf);

        return $this->permissionsIn($gym)->intersect($anyOf)->isNotEmpty();
    }

    /** This person's owner-made roles in one gym. Explicit gym filter, so it works outside a request too. */
    public function gymRoles(Gym|int $gym)
    {
        return GymRole::withoutGlobalScope('gym')
            ->where('gym_id', $gym instanceof Gym ? $gym->id : $gym)
            ->whereHas('users', fn ($q) => $q->whereKey($this->id));
    }

    /** Pay stays hidden unless the viewer owns the gym, runs HR, or sees payroll or finance (R-23). */
    public function canViewCompensation(Gym|int $gym): bool
    {
        return $this->hasPermission($gym, 'hr.staff.manage', 'payroll.runs.view', 'finance.payments.view');
    }

    public function canApproveLeave(Gym|int $gym): bool
    {
        return $this->hasPermission($gym, 'hr.leave.manage');
    }
}
