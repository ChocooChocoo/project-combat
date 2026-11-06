<?php

namespace App\Support\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplyNeedStatus;
use App\Models\Gym;
use App\Models\StockItem;
use App\Support\CurrentGym;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * R-75: what to restock. The stock minimum, activity needs and stock already on
 * order are three aggregates merged in PHP, not one query per item.
 */
class Restock
{
    public function forGym(Gym $gym): Collection
    {
        app(CurrentGym::class)->set($gym);

        /** @var Collection<int, StockItem> $items */
        $items = $gym->stockItems()->active()->orderBy('name')->orderBy('id')->get();

        $openNeeds = DB::table('supply_needs')
            ->where('gym_id', $gym->id)
            ->where('status', SupplyNeedStatus::Open->value)
            ->where(function ($query) {
                $query->whereNull('needed_by')
                    ->orWhereDate('needed_by', '<=', today()->addDays(14)->toDateString());
            })
            ->selectRaw('stock_item_id, SUM(quantity) as total')
            ->groupBy('stock_item_id')
            ->pluck('total', 'stock_item_id');

        $onOrder = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.gym_id', $gym->id)
            ->whereIn('purchase_orders.status', [
                PurchaseOrderStatus::Pending->value,
                PurchaseOrderStatus::PartlyReceived->value,
            ])
            ->selectRaw('purchase_order_lines.stock_item_id, SUM(CASE WHEN quantity_ordered > quantity_received THEN quantity_ordered - quantity_received ELSE 0 END) as total')
            ->groupBy('purchase_order_lines.stock_item_id')
            ->pluck('total', 'purchase_order_lines.stock_item_id');

        return $items->map(function (StockItem $item) use ($openNeeds, $onOrder) {
            $needed = (int) ($openNeeds[$item->id] ?? 0);
            $ordered = (int) ($onOrder[$item->id] ?? 0);
            $minimum = $item->min_level ?? 0;
            $shortfall = max(0, $minimum + $needed - $item->quantity - $ordered);
            $atOrBelow = $item->min_level !== null && $item->quantity <= $item->min_level;

            if (! $atOrBelow && $shortfall === 0) {
                return null;
            }

            return [
                'stock_item_id' => $item->id,
                'name' => $item->name,
                'category' => $item->category->value,
                'unit' => $item->unit,
                'location' => $item->location,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'sku' => $item->sku,
                'min_level' => $item->min_level,
                'reorder_qty' => $item->reorder_qty,
                'on_order' => $ordered,
                'open_needs' => $needed,
                'shortfall' => $shortfall,
                'suggested_quantity' => max($shortfall, $item->reorder_qty ?? 0),
            ];
        })->filter()->values();
    }
}
