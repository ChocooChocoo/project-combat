<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'stock_item_id', 'type', 'quantity', 'balance_after', 'note', 'actor_id', 'occurred_at',
    'issued_to_user_id', 'returns_movement_id', 'goods_receipt_line_id', 'payment_id',
])]
class StockMovement extends Model
{
    use BelongsToGym;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements cannot be changed. Record an adjustment instead.'));
        static::deleting(fn () => throw new \LogicException('Stock movements cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['type' => StockMovementType::class, 'quantity' => 'integer', 'balance_after' => 'integer', 'occurred_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function returnsMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'returns_movement_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'returns_movement_id');
    }
}
