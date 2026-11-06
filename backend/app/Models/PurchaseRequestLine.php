<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_request_id', 'stock_item_id', 'quantity', 'est_unit_cost', 'supply_need_id'])]
class PurchaseRequestLine extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'est_unit_cost' => 'decimal:2'];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function supplyNeed(): BelongsTo
    {
        return $this->belongsTo(SupplyNeed::class);
    }
}
