<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStage;
use App\Enums\CustomerStatus;
use App\Enums\MembershipStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One prospect or customer record (R-69). The stage is derived, never stored. */
#[Fillable([
    'name', 'email', 'phone', 'source', 'inquiry', 'user_id', 'assigned_to', 'status', 'created_by',
])]
class Customer extends Model
{
    use BelongsToGym;

    protected $appends = ['stage'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'source' => CustomerSource::class,
            'status' => CustomerStatus::class,
            'linked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A linked membership belongs to the same gym, so its own scope is redundant here. */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withoutGlobalScope('gym');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CustomerFollowUp::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_members')->withTimestamps();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** R-71: lost overrides everything; otherwise the linked membership decides. */
    public function getStageAttribute(): string
    {
        if ($this->status === CustomerStatus::Lost) {
            return CustomerStage::Lost->value;
        }

        if (! $this->membership_id) {
            return CustomerStage::Lead->value;
        }

        $membership = $this->relationLoaded('membership') ? $this->membership : $this->membership()->first();

        if (! $membership) {
            return CustomerStage::Lead->value;
        }

        if (in_array($membership->status, [MembershipStatus::Applied, MembershipStatus::Rejected, MembershipStatus::AwaitingPayment], true)) {
            return CustomerStage::Applied->value;
        }

        if ($membership->starts_on && $membership->starts_on->lte(today())) {
            return CustomerStage::Member->value;
        }

        return CustomerStage::Applied->value;
    }
}
