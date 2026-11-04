<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A service a plan does not include, priced on its own (R-29). */
#[Fillable(['membership_plan_id', 'gym_service_id', 'price'])]
class MembershipPlanAddon extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GymService::class, 'gym_service_id');
    }
}
