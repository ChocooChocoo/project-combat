<?php

namespace App\Models;

use App\Enums\PlanBilling;
use App\Models\Concerns\BelongsToGym;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One all-inclusive membership plan with its fee, inclusions and policies (R-29). */
#[Fillable([
    'name', 'billing_type', 'duration_months', 'session_count', 'total_fee', 'registration_included',
    'inclusions', 'policy_validity', 'policy_freezing', 'policy_renewal', 'policy_cancellation', 'max_freeze_days', 'refundable',
])]
class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use BelongsToGym, HasFactory;

    protected function casts(): array
    {
        return [
            'billing_type' => PlanBilling::class,
            'duration_months' => 'integer',
            'session_count' => 'integer',
            'total_fee' => 'decimal:2',
            'registration_included' => 'boolean',
            'max_freeze_days' => 'integer',
            'refundable' => 'boolean',
            'inclusions' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function addons(): HasMany
    {
        return $this->hasMany(MembershipPlanAddon::class);
    }
}
