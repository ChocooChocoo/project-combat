<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * R-111: a promo (applies by itself) or a voucher (needs its code) on chosen membership plans.
 * Status and decision fields are set by the system only.
 */
#[Fillable(['kind', 'name', 'code', 'discount_type', 'value', 'starts_on', 'ends_on', 'max_uses'])]
class Offer extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(MembershipPlan::class, 'offer_plan')->withoutGlobalScope('gym');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Off this charge, never more than the charge itself. */
    public function discountOn(float $charge): float
    {
        $off = $this->discount_type === 'percent' ? $charge * (float) $this->value / 100 : (float) $this->value;

        return round(min(max($off, 0), $charge), 2);
    }
}
