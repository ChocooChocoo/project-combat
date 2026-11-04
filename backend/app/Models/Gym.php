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
}
