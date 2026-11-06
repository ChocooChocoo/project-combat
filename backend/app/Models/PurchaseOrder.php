<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-77, R-79: one order to one supplier, with receipts and status. */
#[Fillable([
    'number', 'supplier_id', 'purchase_request_id', 'is_direct', 'status', 'ordered_by',
    'expected_on', 'total', 'closed_short', 'cancel_reason',
])]
class PurchaseOrder extends Model
{
    use BelongsToGym;

    protected $attributes = ['status' => 'pending', 'is_direct' => false, 'closed_short' => false];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'is_direct' => 'boolean',
            'closed_short' => 'boolean',
            'expected_on' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseOrderStatus::Pending;
    }

    public function isPartlyReceived(): bool
    {
        return $this->status === PurchaseOrderStatus::PartlyReceived;
    }

    public function isOpen(): bool
    {
        return $this->status->open();
    }
}
