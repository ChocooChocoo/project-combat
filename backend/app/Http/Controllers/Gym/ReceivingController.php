<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Gym;
use App\Support\Procurement\Receiving;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** R-78: record a full or partial delivery and let the services update stock and money. */
class ReceivingController extends Controller
{
    use PresentsProcurement;

    public function store(Request $request, Gym $gym, int $order, Receiving $receiving): JsonResponse
    {
        $row = $gym->purchaseOrders()->findOrFail($order);
        $this->normalizeLines($request);

        $data = $request->validate([
            'received_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.stock_item_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        [$updated, $receipt] = $receiving->receive(
            $gym,
            $row,
            $data['lines'],
            $request->user(),
            isset($data['received_on']) ? Carbon::parse($data['received_on']) : today(),
            $data['note'] ?? null,
        );

        return response()->json([
            'order' => $this->orderPayload($updated),
            'receipt' => $this->receiptPayload($receipt),
        ], 201);
    }

    /** Accepts the line list or a map of purchase order line id to quantity. */
    private function normalizeLines(Request $request): void
    {
        $lines = $request->input('lines');

        if (! is_array($lines) || $lines === []) {
            return;
        }

        if (array_is_list($lines)) {
            $request->merge(['lines' => array_map(function ($line) {
                if (is_array($line) && empty($line['purchase_order_line_id']) && ! empty($line['line_id'])) {
                    $line['purchase_order_line_id'] = $line['line_id'];
                }

                return $line;
            }, $lines)]);

            return;
        }

        $normalized = [];

        foreach ($lines as $id => $line) {
            if (is_array($line)) {
                $normalized[] = ['purchase_order_line_id' => (int) $id, ...$line];
            } else {
                $normalized[] = ['purchase_order_line_id' => (int) $id, 'quantity' => $line];
            }
        }

        $request->merge(['lines' => $normalized]);
    }
}
