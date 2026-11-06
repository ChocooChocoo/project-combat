<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** R-76: a request for items that a different permitted person decides. */
#[Fillable([
    'number', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_reason',
    'needed_by', 'note',
])]
class PurchaseRequest extends Model
{
    use BelongsToGym;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'decided_at' => 'datetime',
            'needed_by' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === PurchaseRequestStatus::Approved;
    }
}
