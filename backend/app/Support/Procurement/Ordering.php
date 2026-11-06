<?php

namespace App\Support\Procurement;

use App\Enums\GymPersonType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\SupplyNeedStatus;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\SupplyNeed;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** R-77, R-79: approve-to-order conversion, direct owner buying and order state. */
class Ordering
{
    public function __construct(private Notifier $notifier) {}

    public function fromRequest(
        Gym $gym,
        PurchaseRequest $request,
        Supplier $supplier,
        array $lines,
        User $actor,
        ?Carbon $expectedOn = null,
    ): PurchaseOrder {
        app(CurrentGym::class)->set($gym);

        abort_unless((int) $request->gym_id === $gym->id, 404);
        abort_unless((int) $supplier->gym_id === $gym->id, 404);
        abort_unless($request->status === PurchaseRequestStatus::Approved, 409, 'Only an approved request becomes a purchase order.');
        abort_if(
            PurchaseOrder::withoutGlobalScope('gym')->where('purchase_request_id', $request->id)->exists(),
            409,
            'This request already has a purchase order.',
        );
        $this->assertSupplierActive($supplier);

        $data = $this->validateLines($lines, requestLines: true);
        $requestLines = $request->lines()->with('stockItem')->get();
        $byId = $requestLines->keyBy('id');
        $byStock = $requestLines->keyBy('stock_item_id');
        $chosen = [];
        $usedNeeds = [];

        foreach ($data as $index => $line) {
            if (empty($line['purchase_request_line_id']) && ! empty($line['request_line_id'])) {
                $line['purchase_request_line_id'] = $line['request_line_id'];
            }

            $requestLine = null;

            if (! empty($line['purchase_request_line_id'])) {
                $requestLine = $byId->get((int) $line['purchase_request_line_id']);
            } elseif (! empty($line['stock_item_id'])) {
                $requestLine = $byStock->get((int) $line['stock_item_id']);
            }

            if (! $requestLine || ! $requestLine->stockItem || (int) $requestLine->stockItem->gym_id !== $gym->id) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_request_line_id" => 'Choose a line from this purchase request.',
                ]);
            }

            if (! empty($line['stock_item_id']) && (int) $line['stock_item_id'] !== (int) $requestLine->stock_item_id) {
                throw ValidationException::withMessages([
                    "lines.{$index}.stock_item_id" => 'That item is not on this request line.',
                ]);
            }

            if (isset($chosen[$requestLine->id])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_request_line_id" => 'Each request line can appear only once.',
                ]);
            }

            if ((int) $line['quantity'] > $requestLine->quantity) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" => "Only {$requestLine->quantity} were requested.",
                ]);
            }

            if ($requestLine->supply_need_id) {
                $usedNeeds[] = $requestLine->supply_need_id;
            }

            $chosen[$requestLine->id] = [
                'quantity' => (int) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
            ];
        }

        return DB::transaction(function () use ($gym, $request, $supplier, $actor, $chosen, $usedNeeds, $expectedOn) {
            /** @var Collection<int, PurchaseRequestLine> $requestLines */
            $requestLines = $request->lines()->whereIn('id', array_keys($chosen))->get()->keyBy('id');

            $totalCents = 0;
            foreach ($requestLines as $line) {
                $detail = $chosen[$line->id];
                $unitCents = $this->cents($detail['unit_cost']);
                $totalCents += $unitCents * $detail['quantity'];
            }

            $order = $gym->purchaseOrders()->create([
                'number' => $this->placeholder(),
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $request->id,
                'is_direct' => false,
                'status' => PurchaseOrderStatus::Pending->value,
                'ordered_by' => $actor->id,
                'expected_on' => $expectedOn?->toDateString(),
                'total' => $this->money($totalCents),
            ]);
            $order->forceFill(['number' => $this->number('PO', $gym->id, $order->id)])->save();

            foreach ($requestLines as $line) {
                $detail = $chosen[$line->id];
                $order->lines()->create([
                    'stock_item_id' => $line->stock_item_id,
                    'quantity_ordered' => $detail['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => number_format((float) $detail['unit_cost'], 2, '.', ''),
                ]);
            }

            if ($usedNeeds !== []) {
                SupplyNeed::query()->whereIn('id', array_values(array_unique($usedNeeds)))
                    ->update(['status' => SupplyNeedStatus::Closed->value]);
            }

            $order = $order->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']);
            $this->notifier->orderStatus($gym, $order, $actor, "Purchase order {$order->number} was raised from request {$request->number}.");

            return $order;
        });
    }

    public function direct(Gym $gym, Supplier $supplier, array $lines, User $owner, ?Carbon $expectedOn = null): PurchaseOrder
    {
        app(CurrentGym::class)->set($gym);

        abort_unless($owner->hasGymRole($gym, GymPersonType::Owner), 403, 'Only the owner can make a direct purchase.');
        abort_unless((int) $supplier->gym_id === $gym->id, 404);
        $this->assertSupplierActive($supplier);

        $data = $this->validateLines($lines, requestLines: false);
        $ids = collect($data)->pluck('stock_item_id')->map(fn ($id) => (int) $id)->unique()->values();
        $items = $gym->stockItems()->whereIn('id', $ids)->get()->keyBy('id');

        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages(['lines' => 'Choose stock items from this gym.']);
        }

        return DB::transaction(function () use ($gym, $supplier, $owner, $data, $items, $expectedOn) {
            $totalCents = 0;
            foreach ($data as $line) {
                $item = $items->get((int) $line['stock_item_id']);
                if ($item->isArchived()) {
                    throw ValidationException::withMessages(['lines' => "{$item->name} is archived."]);
                }
                $totalCents += $this->cents($line['unit_cost']) * (int) $line['quantity'];
            }

            $order = $gym->purchaseOrders()->create([
                'number' => $this->placeholder(),
                'supplier_id' => $supplier->id,
                'purchase_request_id' => null,
                'is_direct' => true,
                'status' => PurchaseOrderStatus::Pending->value,
                'ordered_by' => $owner->id,
                'expected_on' => $expectedOn?->toDateString(),
                'total' => $this->money($totalCents),
            ]);
            $order->forceFill(['number' => $this->number('PO', $gym->id, $order->id)])->save();

            foreach ($data as $line) {
                $order->lines()->create([
                    'stock_item_id' => (int) $line['stock_item_id'],
                    'quantity_ordered' => (int) $line['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => number_format((float) $line['unit_cost'], 2, '.', ''),
                ]);
            }

            $order = $order->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']);
            $this->notifier->orderStatus($gym, $order, $owner, "Owner direct purchase {$order->number} was created.");

            return $order;
        });
    }

    public function cancel(PurchaseOrder $order, string $reason, User $actor): PurchaseOrder
    {
        $gym = Gym::findOrFail($order->gym_id);
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $order, $reason, $actor) {
            $locked = $gym->purchaseOrders()->lockForUpdate()->findOrFail($order->id);

            abort_unless($locked->status === PurchaseOrderStatus::Pending, 409, 'Only a pending order can be cancelled.');
            abort_if(
                $locked->receipts()->exists() || $locked->lines()->sum('quantity_received') > 0,
                409,
                'Received stock cannot be cancelled.',
            );

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Cancelled->value,
                'cancel_reason' => trim($reason),
            ])->save();

            $this->reopenNeeds($locked);

            $locked = $locked->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']);
            $this->notifier->orderStatus($gym, $locked, $actor, "Purchase order {$locked->number} was cancelled.");

            return $locked;
        });
    }

    public function closeShort(PurchaseOrder $order, string $reason, User $actor): PurchaseOrder
    {
        $gym = Gym::findOrFail($order->gym_id);
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $order, $reason, $actor) {
            $locked = $gym->purchaseOrders()->lockForUpdate()->findOrFail($order->id);

            abort_unless($locked->status === PurchaseOrderStatus::PartlyReceived, 409, 'Only a partly received order can be closed with a remainder.');

            // The schema has one free-text reason column for an order that ends early.
            $locked->forceFill([
                'status' => PurchaseOrderStatus::Completed->value,
                'closed_short' => true,
                'cancel_reason' => trim($reason),
            ])->save();

            $locked = $locked->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']);
            $this->notifier->orderStatus($gym, $locked, $actor, "Purchase order {$locked->number} was closed with a remainder.");

            return $locked;
        });
    }

    /** Recomputes pending, partly received or completed from the line counts. */
    public function refreshStatus(PurchaseOrder $order): PurchaseOrder
    {
        $gym = Gym::findOrFail($order->gym_id);
        app(CurrentGym::class)->set($gym);

        $ordered = (int) $order->lines()->sum('quantity_ordered');
        $received = (int) $order->lines()->sum('quantity_received');
        $status = match (true) {
            $received <= 0 => PurchaseOrderStatus::Pending,
            $received >= $ordered => PurchaseOrderStatus::Completed,
            default => PurchaseOrderStatus::PartlyReceived,
        };

        if ($order->status !== PurchaseOrderStatus::Cancelled
            && ! ($order->status === PurchaseOrderStatus::Completed && $order->closed_short)
            && $order->status !== $status) {
            $order->forceFill(['status' => $status->value])->save();
        }

        return $order->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']);
    }

    private function assertSupplierActive(Supplier $supplier): void
    {
        if (! $supplier->isActive()) {
            throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier.']);
        }
    }

    private function validateLines(array $lines, bool $requestLines): array
    {
        if ($lines !== [] && ! array_is_list($lines)) {
            $normalized = [];
            $key = $requestLines ? 'purchase_request_line_id' : 'stock_item_id';

            foreach ($lines as $id => $line) {
                if (is_array($line)) {
                    $normalized[] = [$key => (int) $id, ...$line];
                }
            }

            $lines = $normalized;
        }

        $rules = [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];

        if ($requestLines) {
            $rules['lines.*.purchase_request_line_id'] = ['sometimes', 'nullable', 'integer', 'min:1'];
            $rules['lines.*.request_line_id'] = ['sometimes', 'nullable', 'integer', 'min:1'];
            $rules['lines.*.stock_item_id'] = ['sometimes', 'nullable', 'integer', 'min:1'];
        } else {
            $rules['lines.*.stock_item_id'] = ['required', 'integer', 'min:1'];
        }

        $data = Validator::make(['lines' => $lines], $rules)->validate();

        foreach ($data['lines'] as $index => $line) {
            if ($requestLines && empty($line['purchase_request_line_id']) && empty($line['request_line_id']) && empty($line['stock_item_id'])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_request_line_id" => 'Choose a request line.',
                ]);
            }
        }

        return $data['lines'];
    }

    private function reopenNeeds(PurchaseOrder $order): void
    {
        $request = $order->purchaseRequest;

        if (! $request) {
            return;
        }

        $ids = $request->lines()->whereNotNull('supply_need_id')->pluck('supply_need_id')->all();

        if ($ids !== []) {
            SupplyNeed::query()->whereIn('id', $ids)->update(['status' => SupplyNeedStatus::Open->value]);
        }
    }

    private function placeholder(): string
    {
        return 'TMP-'.Str::upper(Str::random(16));
    }

    private function number(string $prefix, int $gymId, int $id): string
    {
        return sprintf('%s-%d-%06d', $prefix, $gymId, $id);
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
