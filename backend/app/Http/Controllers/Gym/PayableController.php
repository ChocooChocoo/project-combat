<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Support\Procurement\Payables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-66: what each supplier is owed, its orders and append-only payments. */
class PayableController extends Controller
{
    use PresentsProcurement;

    public function index(Gym $gym, Payables $payables): JsonResponse
    {
        return response()->json(['payables' => $payables->summary($gym)->values()]);
    }

    public function show(Gym $gym, int $supplier, Payables $payables): JsonResponse
    {
        $row = $gym->suppliers()->findOrFail($supplier);
        $data = $payables->forSupplier($gym, $row);

        return response()->json([
            'supplier' => $this->supplierPayload($data['supplier']),
            'orders' => $data['orders']->map(fn (PurchaseOrder $order) => [
                ...$this->orderPayload($order),
                'received_total' => number_format((float) ($order->receipts_sum_total ?? 0), 2, '.', ''),
            ])->values(),
            'payments' => $data['payments']->map(fn (SupplierPayment $payment) => $this->supplierPaymentPayload($payment))->values(),
            'received' => $data['received'],
            'paid' => $data['paid'],
            'outstanding' => $data['outstanding'],
        ]);
    }

    public function pay(Request $request, Gym $gym, Payables $payables): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'not_in:0', 'max:9999999999.99', 'decimal:0,2'],
            'method' => ['required', Rule::in(['cash', 'card', 'ewallet', 'bank'])],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'purchase_order_id' => ['nullable', 'integer', 'min:1'],
            'corrects_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $supplier = $gym->suppliers()->findOrFail($data['supplier_id']);
        $payment = $payables->pay($supplier, $data['amount'], $data, $request->user());

        return response()->json(['payment' => $this->supplierPaymentPayload($payment)], 201);
    }
}
