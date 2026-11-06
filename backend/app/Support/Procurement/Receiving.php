<?php

namespace App\Support\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\Finance\Expenses;
use App\Support\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * R-78: one delivery is one transaction. The order is locked, quantities are
 * checked, stock and the receipt are written, then exactly one expense posts.
 */
class Receiving
{
    public function __construct(
        private StockLedger $ledger,
        private Expenses $expenses,
        private Ordering $ordering,
        private Notifier $notifier,
    ) {}

    /** @return array{order: PurchaseOrder, receipt: GoodsReceipt} */
    public function receive(
        Gym $gym,
        PurchaseOrder $order,
        array $lines,
        User $actor,
        Carbon $date,
        ?string $note = null,
    ): array {
        app(CurrentGym::class)->set($gym);

        return DB::transaction(function () use ($gym, $order, $lines, $actor, $date, $note) {
            $locked = $gym->purchaseOrders()->lockForUpdate()->findOrFail($order->id);
            $requested = $this->requestedLines($locked, $lines);

            $duplicate = $this->duplicateReceipt($locked, $requested, $actor, $date, $note);
            if ($duplicate) {
                return [$locked->fresh(['supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem']), $duplicate];
            }

            abort_unless($locked->isOpen(), 409, 'This purchase order is no longer open for receiving.');

            $orderLines = $locked->lines()->get()->keyBy('id');

            foreach ($requested as $lineId => $quantity) {
                $line = $orderLines->get($lineId);

                if ((int) $line->quantity_received + $quantity > (int) $line->quantity_ordered) {
                    throw ValidationException::withMessages([
                        'lines' => "Only {$line->remaining()} of {$line->stockItem?->name} remain to receive.",
                    ]);
                }
            }

            $receipt = $gym->goodsReceipts()->create([
                'number' => 'TMP-'.Str::upper(Str::random(16)),
                'purchase_order_id' => $locked->id,
                'received_by' => $actor->id,
                'received_on' => $date->toDateString(),
                'note' => $note,
                'total' => '0.00',
            ]);
            $receipt->forceFill(['number' => sprintf('GR-%d-%06d', $gym->id, $receipt->id)])->save();

            $totalCents = 0;

            foreach ($requested as $lineId => $quantity) {
                $line = $orderLines->get($lineId);
                $item = $gym->stockItems()->lockForUpdate()->findOrFail($line->stock_item_id);
                $amountCents = $this->cents($line->unit_cost) * $quantity;

                $receiptLine = $receipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'quantity' => $quantity,
                    'unit_cost' => $line->unit_cost,
                    'amount' => $this->money($amountCents),
                ]);

                $this->ledger->move($item, 'receipt', $quantity, $actor, [
                    'goods_receipt_line_id' => $receiptLine->id,
                    'occurred_at' => $date->copy()->setTimeFrom(now()),
                ]);

                $line->forceFill(['quantity_received' => (int) $line->quantity_received + $quantity])->save();
                $totalCents += $amountCents;
            }

            $receipt->forceFill(['total' => $this->money($totalCents)])->save();

            $locked = $this->ordering->refreshStatus($locked);
            $receipt = $receipt->fresh(['receivedBy', 'lines.purchaseOrderLine.stockItem']);

            if ($totalCents > 0) {
                $this->expenses->post(
                    $gym,
                    'purchases',
                    $this->money($totalCents),
                    $date,
                    'goods_receipt',
                    $receipt->id,
                    $actor,
                );
            }

            $message = $locked->status === PurchaseOrderStatus::Completed
                ? "Purchase order {$locked->number} was fully received."
                : "Part of purchase order {$locked->number} was received.";
            $this->notifier->orderStatus($gym, $locked, $actor, $message);

            return [$locked, $receipt];
        });
    }

    /** @return array<int, int> purchase order line id => quantity */
    private function requestedLines(PurchaseOrder $order, array $lines): array
    {
        if ($lines !== [] && ! array_is_list($lines)) {
            $normalized = [];

            foreach ($lines as $id => $line) {
                $normalized[] = is_array($line)
                    ? ['purchase_order_line_id' => (int) $id, ...$line]
                    : ['purchase_order_line_id' => (int) $id, 'quantity' => $line];
            }

            $lines = $normalized;
        }

        $data = Validator::make(['lines' => $lines], [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.line_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.stock_item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ])->validate();

        $orderLines = $order->lines()->get();
        $byId = $orderLines->keyBy('id');
        $byStock = $orderLines->groupBy('stock_item_id');
        $requested = [];

        foreach ($data['lines'] as $index => $line) {
            if (empty($line['purchase_order_line_id']) && ! empty($line['line_id'])) {
                $line['purchase_order_line_id'] = $line['line_id'];
            }

            $orderLine = null;

            if (! empty($line['purchase_order_line_id'])) {
                $orderLine = $byId->get((int) $line['purchase_order_line_id']);
            } elseif (! empty($line['stock_item_id'])) {
                $matches = $byStock->get((int) $line['stock_item_id'], collect());
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => 'Choose the order line, not only the item.',
                    ]);
                }
                $orderLine = $matches->first();
            }

            if (! $orderLine) {
                throw ValidationException::withMessages([
                    "lines.{$index}.purchase_order_line_id" => 'Choose a line from this purchase order.',
                ]);
            }

            $quantity = (int) $line['quantity'];
            if ($quantity === 0) {
                continue;
            }

            $requested[$orderLine->id] = ($requested[$orderLine->id] ?? 0) + $quantity;
        }

        if ($requested === []) {
            throw ValidationException::withMessages(['lines' => 'Enter at least one received quantity.']);
        }

        ksort($requested);

        return $requested;
    }

    /** A retried submission with the same delivery details returns the first receipt. */
    private function duplicateReceipt(
        PurchaseOrder $order,
        array $requested,
        User $actor,
        Carbon $date,
        ?string $note,
    ): ?GoodsReceipt {
        $receipts = $order->receipts()
            ->with('lines')
            ->where('received_by', $actor->id)
            ->whereDate('received_on', $date->toDateString())
            ->where('note', $note)
            ->orderBy('id')
            ->get();

        foreach ($receipts as $receipt) {
            $existing = $receipt->lines
                ->groupBy('purchase_order_line_id')
                ->map(fn ($lines) => (int) $lines->sum('quantity'))
                ->sortKeys()
                ->all();

            if ($existing === $requested) {
                return $receipt->fresh(['receivedBy', 'lines.purchaseOrderLine.stockItem']);
            }
        }

        return null;
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
