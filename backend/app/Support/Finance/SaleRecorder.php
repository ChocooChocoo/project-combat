<?php

namespace App\Support\Finance;

use App\Enums\PaymentMethod;
use App\Enums\StockCategory;
use App\Enums\StockItemStatus;
use App\Enums\StockMovementType;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\StockLedger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R-63: a desk sale is one payment, and for merchandise one sale movement per
 * line. Walk-in and other sales write no booking and no membership.
 */
class SaleRecorder
{
    public const TYPES = ['walk_in', 'merchandise', 'other'];

    public function record(Gym $gym, User $actor, array $data): Payment
    {
        $data = Validator::make($data, [
            'income_type' => ['required', Rule::in(self::TYPES)],
            'method' => ['required', Rule::in(array_keys(config('membership.desk_methods')))],
            'amount' => ['exclude_if:income_type,merchandise', 'required', 'numeric', 'gt:0', 'max:9999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'user_id' => ['nullable', 'integer', Rule::exists('gym_user', 'user_id')->where('gym_id', $gym->id)],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['exclude_unless:income_type,merchandise', 'required', 'array', 'min:1'],
            'items.*.stock_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ])->validate();

        $type = $data['income_type'];
        $method = PaymentMethod::from($data['method']);
        $userId = $data['user_id'] ?? null;
        $payerName = trim((string) ($data['payer_name'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));

        if ($userId === null && $payerName === '') {
            throw ValidationException::withMessages(['payer_name' => 'Enter the buyer name or choose a member.']);
        }

        $discount = $this->cents($data['discount'] ?? 0);

        return DB::transaction(function () use ($gym, $actor, $type, $method, $userId, $payerName, $note, $discount, $data) {
            app(CurrentGym::class)->set($gym);

            $lines = [];
            $gross = 0;

            if ($type === 'merchandise') {
                $lines = collect($data['items'])->map(fn (array $line) => [
                    'stock_item_id' => (int) $line['stock_item_id'],
                    'quantity' => (int) $line['quantity'],
                ])->values();

                $ids = $lines->pluck('stock_item_id')->unique()->values();
                $items = $gym->stockItems()->lockForUpdate()->whereIn('id', $ids)->get()->keyBy('id');

                if ($items->count() !== $ids->count()) {
                    $missing = $ids->diff($items->keys())->values()->all();

                    throw (new ModelNotFoundException)->setModel(StockItem::class, $missing);
                }

                foreach ($lines as $index => $line) {
                    $item = $items->get($line['stock_item_id']);

                    if ($item->category !== StockCategory::Merchandise) {
                        throw ValidationException::withMessages(['items' => "{$item->name} is not merchandise for sale."]);
                    }

                    if ($item->status !== StockItemStatus::Active) {
                        throw ValidationException::withMessages(['items' => "{$item->name} is archived and cannot be sold."]);
                    }

                    if ($item->sale_price === null || (float) $item->sale_price <= 0) {
                        throw ValidationException::withMessages(['items' => "{$item->name} has no sale price."]);
                    }

                    if ($item->quantity < $line['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Only {$item->quantity} {$item->unitLabel($item->quantity)} of {$item->name} in stock.",
                        ]);
                    }

                    $line['item'] = $item;
                    $line['total'] = $this->cents($item->sale_price) * $line['quantity'];
                    $gross += $line['total'];
                    $lines[$index] = $line;
                }
            } else {
                $gross = $this->cents($data['amount']);
            }

            if ($discount > $gross) {
                throw ValidationException::withMessages(['discount' => 'The discount cannot be larger than the sale total.']);
            }

            $payment = Payment::create([
                'membership_id' => null,
                'session_booking_id' => null,
                'user_id' => $userId,
                'amount' => $this->money($gross - $discount),
                'discount' => $this->money($discount),
                'method' => $method,
                'received_by' => $actor->id,
                'paid_at' => now(),
                'income_type' => $type,
                'payer_name' => $payerName === '' ? null : $payerName,
                'note' => $note === '' ? null : $note,
            ]);
            $payment->receipt_number = sprintf('OR-%d-%06d', $payment->gym_id, $payment->id);
            $payment->save();

            if ($type === 'merchandise') {
                foreach ($lines as $line) {
                    app(StockLedger::class)->move(
                        $line['item'], StockMovementType::Sale, $line['quantity'], $actor,
                        ['payment_id' => $payment->id],
                    );
                }
            }

            return $payment;
        });
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
