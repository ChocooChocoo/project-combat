<?php

namespace App\Models;

use App\Enums\StockCategory;
use App\Enums\StockItemStatus;
use App\Models\Concerns\BelongsToGym;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'sku', 'category', 'unit', 'location', 'min_level', 'reorder_qty', 'unit_cost', 'sale_price'])]
class StockItem extends Model
{
    use BelongsToGym;

    public const UNITS = ['piece', 'pair', 'box', 'litre', 'kg'];

    protected $attributes = ['quantity' => 0, 'status' => 'active'];

    private static bool $ledgerWrite = false;

    /** @internal Only StockLedger changes the cached movement sum. */
    public static function throughLedger(Closure $write): mixed
    {
        $previous = self::$ledgerWrite;
        self::$ledgerWrite = true;
        try {
            return $write();
        } finally {
            self::$ledgerWrite = $previous;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (StockItem $item) {
            if ((int) $item->quantity !== 0 && ! self::$ledgerWrite) {
                throw new LogicException('Stock is added with StockLedger::open(), which records the opening movement.');
            }
        });
        static::updating(function (StockItem $item) {
            if ($item->isDirty('quantity') && ! self::$ledgerWrite) {
                throw new LogicException('Stock quantity changes only through a stock movement.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'category' => StockCategory::class, 'status' => StockItemStatus::class,
            'quantity' => 'integer', 'min_level' => 'integer', 'reorder_qty' => 'integer',
            'unit_cost' => 'decimal:2', 'sale_price' => 'decimal:2', 'low_notified_at' => 'datetime',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', StockItemStatus::Active->value);
    }

    public function scopeLow(Builder $query): void
    {
        $query->whereNotNull('min_level')->whereColumn('quantity', '<=', 'min_level');
    }

    public function unitLabel(int $quantity): string
    {
        return match ($this->unit) {
            'piece' => $quantity === 1 ? 'piece' : 'pieces',
            'pair' => $quantity === 1 ? 'pair' : 'pairs',
            'box' => $quantity === 1 ? 'box' : 'boxes',
            'litre' => $quantity === 1 ? 'litre' : 'litres',
            default => 'kg',
        };
    }

    public function isLow(): bool
    {
        return $this->min_level !== null && $this->quantity <= $this->min_level;
    }

    public function isArchived(): bool
    {
        return $this->status === StockItemStatus::Archived;
    }
}
