<?php

namespace App\Models;

use App\Enums\SupplyNeedStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-75: a hand-entered need for an upcoming class, event or date. */
#[Fillable(['stock_item_id', 'training_session_id', 'needed_by', 'quantity', 'note', 'status', 'created_by'])]
class SupplyNeed extends Model
{
    use BelongsToGym;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'quantity' => 'integer',
            'status' => SupplyNeedStatus::class,
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestLines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function isOpen(): bool
    {
        return $this->status === SupplyNeedStatus::Open;
    }
}
