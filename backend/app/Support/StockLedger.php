<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\Gym;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\StockLow;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/** R-72 to R-74: every stock quantity change is a movement. */
class StockLedger
{
    private const ONLY_FOR = [
        'issued_to_user_id' => StockMovementType::Issue,
        'returns_movement_id' => StockMovementType::Return,
        'goods_receipt_line_id' => StockMovementType::Receipt,
        'payment_id' => StockMovementType::Sale,
    ];

    public function open(Gym $gym, array $item, int $quantity, User $actor): StockItem
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'The opening quantity cannot be below zero.']);
        }

        return DB::transaction(function () use ($gym, $item, $quantity, $actor) {
            $stock = $gym->stockItems()->create(Arr::except($item, ['quantity']));
            $this->record($stock, StockMovementType::Opening, $quantity, $actor, []);

            return $stock->refresh();
        });
    }

    /** Fixed-direction types take positive counts; adjustment takes a signed change. */
    public function move(StockItem $item, string|StockMovementType $type, int $quantity, User $actor, array $extra = []): StockMovement
    {
        $type = $type instanceof StockMovementType ? $type : StockMovementType::tryFrom($type)
            ?? throw ValidationException::withMessages(['type' => 'Choose a movement type.']);
        if ($type === StockMovementType::Opening) {
            throw ValidationException::withMessages(['type' => 'The opening count is recorded when the item is added.']);
        }

        return $this->record($item, $type, $quantity, $actor, $extra);
    }

    private function record(StockItem $item, StockMovementType $type, int $quantity, User $actor, array $extra): StockMovement
    {
        foreach ($extra as $key => $value) {
            if (! in_array($key, ['note', 'occurred_at'], true) && ! array_key_exists($key, self::ONLY_FOR)) {
                throw new LogicException("Unknown stock movement detail: {$key}.");
            }
            if ($value !== null && isset(self::ONLY_FOR[$key]) && self::ONLY_FOR[$key] !== $type) {
                throw new LogicException("Stock movement detail {$key} is not allowed for {$type->value}.");
            }
        }
        $validQuantity = match ($type) {
            StockMovementType::Opening => $quantity >= 0,
            StockMovementType::Adjustment => $quantity !== 0,
            default => $quantity > 0,
        };
        if (! $validQuantity) {
            throw ValidationException::withMessages(['quantity' => 'Enter a valid non-zero quantity; only adjustments may be negative.']);
        }
        $signed = $type === StockMovementType::Adjustment ? $quantity : $type->direction() * $quantity;
        $note = trim($extra['note'] ?? '');
        if (($type->needsNote() && $note === '') || mb_strlen($note) > 255) {
            throw ValidationException::withMessages(['note' => 'Give a reason for this change, at most 255 characters.']);
        }

        return DB::transaction(function () use ($item, $type, $quantity, $signed, $note, $actor, $extra) {
            $locked = StockItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->isArchived()) {
                throw ValidationException::withMessages(['item' => 'This item is archived. Archived items cannot be moved.']);
            }
            if ($type === StockMovementType::Issue && isset($extra['issued_to_user_id'])
                && ! $locked->gym->people()->whereKey($extra['issued_to_user_id'])->exists()) {
                throw ValidationException::withMessages(['issued_to_user_id' => 'Choose a person from this gym.']);
            }
            if ($type === StockMovementType::Return) {
                $issue = StockMovement::query()->whereKey($extra['returns_movement_id'] ?? 0)
                    ->where('stock_item_id', $locked->id)->where('type', StockMovementType::Issue->value)->first();
                if (! $issue) {
                    throw ValidationException::withMessages(['returns_movement_id' => 'Choose an issue of this item to return against.']);
                }
                $outstanding = abs($issue->quantity) - (int) $issue->returns()->sum('quantity');
                if ($quantity > $outstanding) {
                    throw ValidationException::withMessages(['quantity' => "Only {$outstanding} from this issue are still out."]);
                }
            }
            $balance = $locked->quantity + $signed;
            if ($balance < 0) {
                throw ValidationException::withMessages(['quantity' => "Only {$locked->quantity} {$locked->unitLabel($locked->quantity)} in stock."]);
            }
            $movement = $locked->movements()->create([
                'type' => $type->value, 'quantity' => $signed, 'balance_after' => $balance,
                'note' => $note === '' ? null : $note, 'actor_id' => $actor->id,
                'occurred_at' => $extra['occurred_at'] ?? now(),
                ...Arr::only($extra, array_keys(self::ONLY_FOR)),
            ]);
            StockItem::throughLedger(fn () => $locked->forceFill(['quantity' => $balance])->save());
            $this->checkLowStock($locked);
            $item->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }

    public function checkLowStock(StockItem $item): void
    {
        if ($item->isArchived()) {
            return;
        }
        if ($item->isLow() && $item->low_notified_at === null) {
            $item->forceFill(['low_notified_at' => now()])->save();
            $gym = Gym::findOrFail($item->gym_id);
            foreach ($gym->peopleWithPermission('inventory.items.manage') as $person) {
                $person->notify(new StockLow($gym, $item));
            }
        } elseif (! $item->isLow() && $item->low_notified_at !== null) {
            $item->forceFill(['low_notified_at' => null])->save();
        }
    }

    public function assertQuantityMatchesMovements(StockItem $item): void
    {
        $cached = (int) StockItem::withoutGlobalScope('gym')->whereKey($item->id)->value('quantity');
        $sum = (int) StockMovement::withoutGlobalScope('gym')->where('stock_item_id', $item->id)->sum('quantity');
        $last = StockMovement::withoutGlobalScope('gym')->where('stock_item_id', $item->id)->orderByDesc('id')->value('balance_after');
        if ($cached !== $sum || (int) ($last ?? 0) !== $sum) {
            throw new LogicException("Stock item {$item->id} shows {$cached}, but its movements add up to {$sum} (last balance ".($last ?? 'none').').');
        }
    }

    public function mismatches(): Collection
    {
        $totals = DB::table('stock_movements')->selectRaw('stock_item_id, SUM(quantity) as total')->groupBy('stock_item_id');

        return DB::table('stock_items')->leftJoinSub($totals, 'm', 'm.stock_item_id', '=', 'stock_items.id')
            ->whereRaw('stock_items.quantity <> COALESCE(m.total, 0)')
            ->orderBy('stock_items.gym_id')->orderBy('stock_items.id')
            ->get(['stock_items.id', 'stock_items.gym_id', 'stock_items.name', 'stock_items.quantity', DB::raw('COALESCE(m.total, 0) as movement_total')]);
    }
}
