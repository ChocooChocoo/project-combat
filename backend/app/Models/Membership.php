<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A membership application and, once approved, the membership itself (R-31 to R-36).
 * Status, dates, money and decision fields are set by the system only, never mass assigned.
 */
#[Fillable([
    'user_id', 'membership_plan_id', 'emergency_contacts', 'background', 'current_rank', 'training_history',
    'health_answers', 'health_notes', 'waivers', 'signature_name', 'signed_on',
])]
#[Hidden(['medical_file_path'])]
class Membership extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'emergency_contacts' => 'array',
            'health_answers' => 'array',
            'waivers' => 'array',
            'signed_on' => 'date',
            'amount_charged' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sessions_remaining' => 'integer',
            'freeze_days_used' => 'integer',
            'frozen_until' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id')->withoutGlobalScope('gym');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope('gym');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MembershipEvent::class)->withoutGlobalScope('gym');
    }

    /** CRM records linked to this application or membership (R-71). */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope('gym');
    }
}
