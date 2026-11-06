<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplyNeed;
use App\Models\User;

/** Shared snake_case shapes for the purchasing screens (R-75 to R-79, R-66). */
trait PresentsProcurement
{
    protected function supplierPayload(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'payment_terms' => $supplier->payment_terms,
            'notes' => $supplier->notes,
            'status' => $supplier->status->value,
        ];
    }

    protected function supplyNeedPayload(SupplyNeed $need): array
    {
        return [
            'id' => $need->id,
            'stock_item_id' => $need->stock_item_id,
            'item_name' => $need->stockItem?->name,
            'training_session_id' => $need->training_session_id,
            'session_name' => $need->trainingSession?->name,
            'needed_by' => $need->needed_by?->toDateString(),
            'quantity' => $need->quantity,
            'note' => $need->note,
            'status' => $need->status->value,
            'created_by_name' => $this->procurementPersonName($need->createdBy),
        ];
    }

    protected function requestPayload(PurchaseRequest $request): array
    {
        $order = $request->relationLoaded('order') ? $request->order : $request->order()->first();

        return [
            'id' => $request->id,
            'number' => $request->number,
            'status' => $request->status->value,
            'requested_by' => $request->requested_by,
            'requested_by_name' => $this->procurementPersonName($request->requestedBy),
            'decided_by' => $request->decided_by,
            'decided_by_name' => $this->procurementPersonName($request->decidedBy),
            'decided_at' => $request->decided_at?->toIso8601String(),
            'decision_reason' => $request->decision_reason,
            'needed_by' => $request->needed_by?->toDateString(),
            'note' => $request->note,
            'created_at' => $request->created_at?->toIso8601String(),
            'purchase_order_id' => $order?->id,
            'purchase_order' => $order ? ['id' => $order->id, 'number' => $order->number] : null,
            'lines' => $request->lines->map(fn (PurchaseRequestLine $line) => [
                'id' => $line->id,
                'stock_item_id' => $line->stock_item_id,
                'item_name' => $line->stockItem?->name,
                'quantity' => $line->quantity,
                'est_unit_cost' => $line->est_unit_cost,
                'supply_need_id' => $line->supply_need_id,
            ])->values(),
        ];
    }

    protected function orderPayload(PurchaseOrder $order): array
    {
        $received = array_key_exists('receipts_sum_total', $order->getAttributes())
            ? (float) $order->receipts_sum_total
            : ($order->relationLoaded('receipts')
                ? (float) $order->receipts->sum('total')
                : (float) $order->receipts()->sum('total'));

        return [
            'id' => $order->id,
            'number' => $order->number,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->name,
            'purchase_request_id' => $order->purchase_request_id,
            'is_direct' => $order->is_direct,
            'status' => $order->status->value,
            'ordered_by' => $order->ordered_by,
            'ordered_by_name' => $this->procurementPersonName($order->orderedBy),
            'expected_on' => $order->expected_on?->toDateString(),
            'total' => $order->total,
            'received_total' => number_format($received, 2, '.', ''),
            'closed_short' => $order->closed_short,
            'cancel_reason' => $order->cancel_reason,
            'close_reason' => $order->closed_short ? $order->cancel_reason : null,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    protected function orderLinePayload(PurchaseOrderLine $line): array
    {
        return [
            'id' => $line->id,
            'stock_item_id' => $line->stock_item_id,
            'item_name' => $line->stockItem?->name,
            'unit' => $line->stockItem?->unit,
            'quantity_ordered' => $line->quantity_ordered,
            'quantity_received' => $line->quantity_received,
            'remaining' => $line->remaining(),
            'unit_cost' => $line->unit_cost,
            'amount' => number_format((float) $line->unit_cost * $line->quantity_ordered, 2, '.', ''),
        ];
    }

    protected function receiptPayload(GoodsReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'purchase_order_id' => $receipt->purchase_order_id,
            'received_on' => $receipt->received_on?->toDateString(),
            'received_by' => $receipt->received_by,
            'received_by_name' => $this->procurementPersonName($receipt->receivedBy),
            'note' => $receipt->note,
            'total' => $receipt->total,
            'created_at' => $receipt->created_at?->toIso8601String(),
            'lines' => $receipt->lines->map(fn (GoodsReceiptLine $line) => [
                'id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
                'stock_item_id' => $line->purchaseOrderLine?->stock_item_id,
                'item_name' => $line->purchaseOrderLine?->stockItem?->name,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
                'amount' => $line->amount,
            ])->values(),
        ];
    }

    protected function supplierPaymentPayload(SupplierPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'supplier_id' => $payment->supplier_id,
            'supplier_name' => $payment->supplier?->name,
            'purchase_order_id' => $payment->purchase_order_id,
            'purchase_order_number' => $payment->purchaseOrder?->number,
            'amount' => $payment->amount,
            'method' => $payment->method,
            'paid_on' => $payment->paid_on?->toDateString(),
            'source_type' => 'supplier_payment',
            'source_id' => $payment->id,
            'reference' => $payment->reference,
            'note' => $payment->note,
            'corrects_id' => $payment->corrects_id,
            'recorded_by_name' => $this->procurementPersonName($payment->recordedBy),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    private function procurementPersonName(?User $user): ?string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : null;
    }
}
