<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Enums\StockMovementType;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;

trait PresentsStock
{
    protected function itemPayload(StockItem $item): array
    {
        return [
            'id' => $item->id, 'name' => $item->name, 'sku' => $item->sku,
            'category' => $item->category->value, 'unit' => $item->unit, 'location' => $item->location,
            'quantity' => $item->quantity, 'min_level' => $item->min_level, 'reorder_qty' => $item->reorder_qty,
            'unit_cost' => $item->unit_cost, 'sale_price' => $item->sale_price, 'status' => $item->status->value,
            'is_low' => $item->isLow(), 'low_notified_at' => $item->low_notified_at?->format('Y-m-d H:i:s'),
            'created_at' => $item->created_at?->format('Y-m-d H:i'), 'updated_at' => $item->updated_at?->format('Y-m-d H:i'),
        ];
    }

    protected function movementPayload(StockMovement $movement): array
    {
        $issue = $movement->type === StockMovementType::Issue;

        return [
            'id' => $movement->id, 'type' => $movement->type->value,
            'quantity' => $movement->quantity, 'balance_after' => $movement->balance_after, 'note' => $movement->note,
            'actor_id' => $movement->actor_id, 'actor_name' => $this->personName($movement->actor),
            'occurred_at' => $movement->occurred_at?->format('Y-m-d H:i:s'),
            'issued_to_user_id' => $movement->issued_to_user_id, 'issued_to_name' => $this->personName($movement->issuedTo),
            'returns_movement_id' => $movement->returns_movement_id,
            'returned' => $issue ? (int) $movement->returned : null,
            'outstanding' => $issue ? abs($movement->quantity) - (int) $movement->returned : null,
            'payment_id' => $movement->payment_id, 'goods_receipt_line_id' => $movement->goods_receipt_line_id,
        ];
    }

    protected function openIssuePayload(StockMovement $movement): array
    {
        return [
            'id' => $movement->id, 'quantity' => abs($movement->quantity),
            'outstanding' => abs($movement->quantity) - (int) $movement->returned,
            'issued_to_name' => $this->personName($movement->issuedTo),
            'occurred_at' => $movement->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function personName(?User $user): ?string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : null;
    }
}
