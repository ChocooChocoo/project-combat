<?php

namespace App\Support\Procurement;

use App\Models\Gym;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-66: the payable is received goods minus supplier payments, never a stored table. */
class Payables
{
    public function forGym(Gym $gym): Collection
    {
        return $this->summary($gym);
    }

    public function summary(Gym $gym): Collection
    {
        app(CurrentGym::class)->set($gym);

        $received = $this->receivedTotals($gym);
        $paid = $this->paidTotals($gym);

        return $gym->suppliers()->orderBy('name')->orderBy('id')->get()
            ->map(function (Supplier $supplier) use ($received, $paid) {
                $receivedCents = $received[$supplier->id] ?? 0;
                $paidCents = $paid[$supplier->id] ?? 0;

                return [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'received' => $this->money($receivedCents),
                    'paid' => $this->money($paidCents),
                    'outstanding' => $this->money(max(0, $receivedCents - $paidCents)),
                ];
            });
    }

    public function forSupplier(Gym $gym, Supplier $supplier): array
    {
        app(CurrentGym::class)->set($gym);

        $receivedCents = $this->receivedTotals($gym)[$supplier->id] ?? 0;
        $paidCents = $this->paidTotals($gym)[$supplier->id] ?? 0;

        $orders = $supplier->purchaseOrders()
            ->with('purchaseRequest')
            ->withSum('receipts', 'total')
            ->orderByDesc('id')
            ->get();

        $payments = $supplier->payments()
            ->with(['recordedBy', 'purchaseOrder', 'corrects', 'supplier'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        return [
            'supplier' => $supplier,
            'orders' => $orders,
            'payments' => $payments,
            'received' => $this->money($receivedCents),
            'paid' => $this->money($paidCents),
            'outstanding' => $this->money(max(0, $receivedCents - $paidCents)),
        ];
    }

    public function pay(Supplier $supplier, string|float|int $amount, array $data, User $actor): SupplierPayment
    {
        $gym = Gym::findOrFail($supplier->gym_id);
        app(CurrentGym::class)->set($gym);

        $data = Validator::make($data, [
            'method' => ['required', Rule::in(['cash', 'card', 'ewallet', 'bank'])],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'purchase_order_id' => ['nullable', 'integer', 'min:1'],
            'corrects_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $cents = (int) round((float) $amount * 100);

        if ($cents === 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
        }

        $paidCents = $this->paidTotals($gym)[$supplier->id] ?? 0;

        if ($cents > 0) {
            $receivedCents = $this->receivedTotals($gym)[$supplier->id] ?? 0;
            $outstanding = max(0, $receivedCents - $paidCents);

            if ($cents > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Only '.$this->money($outstanding).' remains outstanding for this supplier.',
                ]);
            }

            if (! empty($data['corrects_id'])) {
                throw ValidationException::withMessages(['corrects_id' => 'A payment cannot also be a correction.']);
            }
        } else {
            $original = $this->originalPayment($supplier, $data['corrects_id'] ?? null);

            if (abs($cents) > $this->cents($original->amount)) {
                throw ValidationException::withMessages(['amount' => 'A correction cannot exceed the original payment.']);
            }

            if ($original->corrections()->exists()) {
                throw ValidationException::withMessages(['corrects_id' => 'This payment was already corrected.']);
            }

            if (! empty($data['purchase_order_id'])
                && (int) $data['purchase_order_id'] !== (int) $original->purchase_order_id) {
                throw ValidationException::withMessages(['purchase_order_id' => 'The correction must stay with the original order, if any.']);
            }

            $data['purchase_order_id'] ??= $original->purchase_order_id;
        }

        if (! empty($data['purchase_order_id'])) {
            $order = $supplier->purchaseOrders()->find($data['purchase_order_id']);

            if (! $order) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Choose an order from this supplier.']);
            }
        }

        return $supplier->payments()->create([
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'amount' => $this->money($cents),
            'method' => $data['method'],
            'paid_on' => $data['paid_on'] ?? today()->toDateString(),
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'corrects_id' => $data['corrects_id'] ?? null,
            'recorded_by' => $actor->id,
        ])->load(['recordedBy', 'purchaseOrder', 'corrects', 'supplier']);
    }

    private function originalPayment(Supplier $supplier, ?int $id): SupplierPayment
    {
        if (! $id) {
            throw ValidationException::withMessages(['corrects_id' => 'Choose the payment this correction reverses.']);
        }

        $original = $supplier->payments()->whereKey($id)->first();

        if (! $original || (float) $original->amount <= 0) {
            throw ValidationException::withMessages(['corrects_id' => 'Choose a payment from this supplier.']);
        }

        return $original;
    }

    /** @return array<int, int> supplier id => cents */
    private function receivedTotals(Gym $gym): array
    {
        return DB::table('goods_receipts')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->where('goods_receipts.gym_id', $gym->id)
            ->selectRaw('purchase_orders.supplier_id as supplier_id, SUM(goods_receipts.total) as total')
            ->groupBy('purchase_orders.supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($total) => $this->cents($total))
            ->all();
    }

    /** @return array<int, int> supplier id => cents, corrections already negated */
    private function paidTotals(Gym $gym): array
    {
        return DB::table('supplier_payments')
            ->where('gym_id', $gym->id)
            ->selectRaw('supplier_id, SUM(amount) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id')
            ->map(fn ($total) => $this->cents($total))
            ->all();
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
