<?php

namespace App\Http\Controllers\Gym;

use App\Enums\StockCategory;
use App\Enums\StockItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsStock;
use App\Models\Gym;
use App\Models\StockItem;
use App\Support\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockItemController extends Controller
{
    use PresentsStock;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::enum(StockCategory::class)],
            'low' => ['nullable', 'boolean'], 'archived' => ['nullable', 'boolean'],
        ]);
        $items = $gym->stockItems()->where('status', ($data['archived'] ?? false) ? 'archived' : 'active')
            ->when($data['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($data['low'] ?? false, fn ($query) => $query->low())
            ->orderBy('name')->orderBy('id')->get();
        $active = $gym->stockItems()->active();

        return response()->json([
            'items' => $items->map(fn (StockItem $item) => $this->itemPayload($item)),
            'summary' => [
                'total' => (clone $active)->count(), 'low' => (clone $active)->low()->count(),
                'out' => (clone $active)->where('quantity', 0)->count(),
            ],
        ]);
    }

    public function show(Gym $gym, int $item): JsonResponse
    {
        return response()->json(['item' => $this->itemPayload($gym->stockItems()->findOrFail($item))]);
    }

    public function store(Request $request, Gym $gym, StockLedger $ledger): JsonResponse
    {
        $data = $request->validate($this->rules($gym) + ['quantity' => ['required', 'integer', 'min:0', 'max:1000000']]);
        $stock = $ledger->open($gym, Arr::except($data, ['quantity']), (int) $data['quantity'], $request->user());

        return response()->json(['item' => $this->itemPayload($stock)], 201);
    }

    public function update(Request $request, Gym $gym, int $item, StockLedger $ledger): JsonResponse
    {
        $stock = $gym->stockItems()->findOrFail($item);
        abort_if($stock->isArchived(), 409, 'Archived items cannot be changed.');
        $data = Arr::except($request->validate($this->rules($gym, $stock)), ['quantity']);
        if (($data['category'] ?? null) !== 'merchandise') {
            $data['sale_price'] = null;
        }
        $stock = DB::transaction(function () use ($gym, $stock, $data, $ledger) {
            $locked = $gym->stockItems()->lockForUpdate()->findOrFail($stock->id);
            abort_if($locked->isArchived(), 409, 'Archived items cannot be changed.');
            $locked->fill($data)->save();
            if ($locked->wasChanged('min_level')) {
                $ledger->checkLowStock($locked);
            }

            return $locked;
        });

        return response()->json(['item' => $this->itemPayload($stock)]);
    }

    public function archive(Gym $gym, int $item): JsonResponse
    {
        $stock = DB::transaction(function () use ($gym, $item) {
            $stock = $gym->stockItems()->lockForUpdate()->findOrFail($item);
            if (! $stock->isArchived()) {
                $stock->forceFill(['status' => StockItemStatus::Archived])->save();
            }

            return $stock;
        });

        return response()->json(['item' => $this->itemPayload($stock)]);
    }

    private function rules(Gym $gym, ?StockItem $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('stock_items', 'sku')->where('gym_id', $gym->id)->ignore($ignore?->id)],
            'category' => ['required', Rule::enum(StockCategory::class)],
            'unit' => ['required', Rule::in(StockItem::UNITS)],
            'location' => ['required', 'string', 'max:120'],
            'min_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reorder_qty' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2', 'prohibited_unless:category,merchandise'],
        ];
    }
}
