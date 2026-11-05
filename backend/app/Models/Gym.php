<?php

namespace App\Models;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Policies\GymApplicationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A gym. Until approved, the same row is its owner application (R-11 to R-18). */
#[Fillable([
    'name', 'tier', 'address_line', 'barangay', 'city', 'province', 'latitude', 'longitude',
    'contact_phone', 'contact_email', 'operating_hours', 'disciplines', 'services', 'facilities',
    'activity_types', 'working_days', 'policies',
])]
#[UsePolicy(GymApplicationPolicy::class)]
class Gym extends Model
{
    use HasFactory;

    /** Profile fields an application needs before it can be sent (R-11). */
    public const REQUIRED_PROFILE = [
        'name' => 'gym name',
        'address_line' => 'street address',
        'barangay' => 'barangay',
        'city' => 'city or municipality',
        'province' => 'province',
        'latitude' => 'map location',
        'longitude' => 'map location',
        'contact_phone' => 'contact number',
        'contact_email' => 'contact email',
        'operating_hours' => 'operating hours',
        'disciplines' => 'combat sports offered',
        'services' => 'services',
        'facilities' => 'facilities',
        'tier' => 'tier',
    ];

    /** Q-21: the default working week. The owner can change it in the workspace settings. */
    public const DEFAULT_WORKING_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

    protected function casts(): array
    {
        return [
            'status' => GymStatus::class,
            'tier' => 'integer',
            'approved_tier' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'operating_hours' => 'array',
            'disciplines' => 'array',
            'services' => 'array',
            'facilities' => 'array',
            'activity_types' => 'array',
            'working_days' => 'array',
            'return_items' => 'array',
            'checklist' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function addPerson(User $user, GymPersonType $role): void
    {
        $this->people()->attach($user->id, ['role' => $role->value]);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GymDocument::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
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

    public function holidays(): HasMany
    {
        return $this->hasMany(GymHoliday::class);
    }

    public function gymServices(): HasMany
    {
        return $this->hasMany(GymService::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function athleteProfiles(): HasMany
    {
        return $this->hasMany(AthleteProfile::class);
    }

    public function athleteEntries(): HasMany
    {
        return $this->hasMany(AthleteEntry::class);
    }

    /** The working week the gym set, or Monday to Friday when it has not set one (Q-21). */
    public function workingDays(): array
    {
        return $this->working_days ?: self::DEFAULT_WORKING_DAYS;
    }
}
