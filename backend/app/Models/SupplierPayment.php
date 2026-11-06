<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-66: cash out that settles a supplier payable. Positive pays, negative corrects. */
#[Fillable([
    'supplier_id', 'purchase_order_id', 'amount', 'method', 'paid_on', 'reference', 'note',
    'corrects_id', 'recorded_by',
])]
class SupplierPayment extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Supplier payments cannot be changed. Record a correction instead.'));
        static::deleting(fn () => throw new \LogicException('Supplier payments cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'corrects_id')->withoutGlobalScope('gym');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'corrects_id');
    }

    public function isCorrection(): bool
    {
        return (float) $this->amount < 0;
    }
}
