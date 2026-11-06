<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\GoodsReceipt;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\Procurement\Ordering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-77, R-79: orders from approved requests, owner direct buying and status actions. */
class PurchaseOrderController extends Controller
{
    use PresentsProcurement;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(PurchaseOrderStatus::cases(), 'value'), 'all'])],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = ($data['status'] ?? null) === 'all' ? null : ($data['status'] ?? null);

        $page = $gym->purchaseOrders()
            ->with(['supplier', 'purchaseRequest', 'orderedBy'])
            ->withSum('receipts', 'total')
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($data['supplier_id'] ?? null, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'orders' => collect($page->items())->map(fn (PurchaseOrder $order) => $this->orderPayload($order))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Gym $gym, int $order): JsonResponse
    {
        $row = $gym->purchaseOrders()
            ->with([
                'supplier', 'purchaseRequest', 'orderedBy', 'lines.stockItem',
                'receipts.receivedBy', 'receipts.lines.purchaseOrderLine.stockItem',
            ])
            ->findOrFail($order);

        return response()->json([
            'order' => $this->orderPayload($row),
            'lines' => $row->lines->map(fn (PurchaseOrderLine $line) => $this->orderLinePayload($line))->values(),
            'receipts' => $row->receipts->sortBy('id')->values()
                ->map(fn (GoodsReceipt $receipt) => $this->receiptPayload($receipt))->values(),
            'timeline' => $this->timeline($row),
        ]);
    }

    public function store(Request $request, Gym $gym, Ordering $ordering): JsonResponse
    {
        if (! $request->filled('purchase_request_id') && $request->filled('request_id')) {
            $request->merge(['purchase_request_id' => $request->input('request_id')]);
        }

        $lines = $request->input('lines');
        if (is_array($lines)) {
            $request->merge(['lines' => array_map(function ($line) {
                if (is_array($line) && empty($line['purchase_request_line_id']) && ! empty($line['request_line_id'])) {
                    $line['purchase_request_line_id'] = $line['request_line_id'];
                }

                return $line;
            }, $lines)]);
        }

        $data = $request->validate([
            'purchase_request_id' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['required', 'integer', 'min:1'],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_request_line_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.stock_item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        $purchaseRequest = $gym->purchaseRequests()->findOrFail($data['purchase_request_id']);
        $supplier = $gym->suppliers()->findOrFail($data['supplier_id']);

        $order = $ordering->fromRequest(
            $gym,
            $purchaseRequest,
            $supplier,
            $data['lines'],
            $request->user(),
            isset($data['expected_on']) ? Carbon::parse($data['expected_on']) : null,
        );

        return response()->json([
            'order' => $this->orderPayload($order->loadMissing(['supplier', 'purchaseRequest', 'orderedBy'])),
        ], 201);
    }

    public function direct(Request $request, Gym $gym, Ordering $ordering): JsonResponse
    {
        abort_unless($request->user()->hasGymRole($gym, GymPersonType::Owner), 403, 'Only the owner can make a direct purchase.');

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'min:1'],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);

        $supplier = $gym->suppliers()->findOrFail($data['supplier_id']);

        $order = $ordering->direct(
            $gym,
            $supplier,
            $data['lines'],
            $request->user(),
            isset($data['expected_on']) ? Carbon::parse($data['expected_on']) : null,
        );

        return response()->json([
            'order' => $this->orderPayload($order->loadMissing(['supplier', 'purchaseRequest', 'orderedBy'])),
        ], 201);
    }

    public function cancel(Request $request, Gym $gym, int $order, Ordering $ordering): JsonResponse
    {
        $row = $gym->purchaseOrders()->findOrFail($order);
        abort_unless($row->isPending(), 409, 'Only a pending order can be cancelled.');
        $reason = $this->reason($request);
        $row = $ordering->cancel($row, $reason, $request->user());

        return response()->json(['order' => $this->orderPayload($row)]);
    }

    public function closeShort(Request $request, Gym $gym, int $order, Ordering $ordering): JsonResponse
    {
        $row = $gym->purchaseOrders()->findOrFail($order);
        abort_unless($row->isPartlyReceived(), 409, 'Only a partly received order can be closed with a remainder.');
        $reason = $this->reason($request, 'Say why the remainder will not arrive.');
        $row = $ordering->closeShort($row, $reason, $request->user());

        return response()->json(['order' => $this->orderPayload($row)]);
    }

    private function reason(Request $request, string $message = 'Say why this order is cancelled.'): string
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
            'close_reason' => ['nullable', 'string', 'max:2000'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $reason = trim((string) ($data['reason'] ?? $data['note'] ?? $data['cancel_reason']
            ?? $data['close_reason'] ?? $data['decision_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => $message,
                'note' => $message,
            ]);
        }

        return $reason;
    }

    private function timeline(PurchaseOrder $order): array
    {
        $timeline = [[
            'at' => $order->created_at?->toIso8601String(),
            'type' => 'ordered',
            'status' => PurchaseOrderStatus::Pending->value,
            'label' => $order->is_direct
                ? 'Owner direct purchase'
                : 'Purchase order raised from '.($order->purchaseRequest?->number ?? 'a request'),
            'by' => $this->procurementPersonName($order->orderedBy),
            'note' => null,
            'amount' => null,
        ]];

        $ordered = $order->lines->mapWithKeys(fn (PurchaseOrderLine $line) => [
            $line->id => $line->quantity_ordered,
        ]);
        $received = $ordered->map(fn () => 0);

        foreach ($order->receipts->sortBy('id') as $receipt) {
            foreach ($receipt->lines as $line) {
                $received[$line->purchase_order_line_id] = ($received[$line->purchase_order_line_id] ?? 0) + $line->quantity;
            }

            $complete = $ordered->every(fn (int $quantity, int $lineId) => ($received[$lineId] ?? 0) >= $quantity);

            $timeline[] = [
                'at' => $receipt->created_at?->toIso8601String() ?? $receipt->received_on?->toIso8601String(),
                'type' => 'receipt',
                'status' => $complete ? PurchaseOrderStatus::Completed->value : PurchaseOrderStatus::PartlyReceived->value,
                'label' => "Goods received ({$receipt->number})",
                'by' => $this->procurementPersonName($receipt->receivedBy),
                'note' => $receipt->note,
                'amount' => $receipt->total,
            ];
        }

        if ($order->status === PurchaseOrderStatus::Cancelled) {
            $timeline[] = [
                'at' => $order->updated_at?->toIso8601String(),
                'type' => 'cancelled',
                'status' => PurchaseOrderStatus::Cancelled->value,
                'label' => 'Order cancelled',
                'by' => null,
                'note' => $order->cancel_reason,
                'amount' => null,
            ];
        }

        if ($order->closed_short) {
            $timeline[] = [
                'at' => $order->updated_at?->toIso8601String(),
                'type' => 'closed_short',
                'status' => PurchaseOrderStatus::Completed->value,
                'label' => 'Closed with remainder',
                'by' => null,
                'note' => $order->cancel_reason,
                'amount' => null,
            ];
        }

        return $timeline;
    }
}
