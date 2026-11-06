<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Shared response shapes for the finance screens (R-63 to R-65). */
trait PresentsFinance
{
    protected function financeMoney(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function financePersonName(?User $user): ?string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : null;
    }

    protected function incomeRow(object $row): array
    {
        $member = trim(implode(' ', array_filter([$row->member_first_name ?? null, $row->member_last_name ?? null])));

        return [
            'id' => (int) $row->id,
            'type' => $row->type,
            'amount' => $this->financeMoney($row->amount),
            'method' => $row->method,
            'paid_at' => Carbon::parse($row->paid_at)->toIso8601String(),
            'member_name' => $member === '' ? null : $member,
            'payer_name' => $row->payer_name,
            'receipt_number' => $row->receipt_number,
            'note' => $row->note,
            'corrects_id' => $row->corrects_id === null ? null : (int) $row->corrects_id,
            'membership_id' => $row->membership_id === null ? null : (int) $row->membership_id,
            'booking_id' => $row->booking_id === null ? null : (int) $row->booking_id,
        ];
    }

    protected function salePayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'type' => $payment->income_type,
            'income_type' => $payment->income_type,
            'amount' => $this->financeMoney($payment->amount),
            'discount' => $this->financeMoney($payment->discount ?? 0),
            'method' => $payment->method->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'user_id' => $payment->user_id,
            'member_name' => $this->financePersonName($payment->relationLoaded('user') ? $payment->user : null),
            'payer_name' => $payment->payer_name,
            'receipt_number' => $payment->receipt_number,
            'note' => $payment->note,
            'corrects_id' => $payment->corrects_id,
            'membership_id' => $payment->membership_id,
            'booking_id' => $payment->session_booking_id,
        ];
    }

    protected function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'category' => $expense->category,
            'amount' => $this->financeMoney($expense->amount),
            'incurred_on' => $expense->incurred_on?->toDateString(),
            'method' => $expense->method,
            'note' => $expense->note,
            'source_type' => $expense->source_type,
            'source_id' => $expense->source_id,
            'corrects_id' => $expense->corrects_id,
            'recorded_by_name' => $this->financePersonName($expense->recordedBy),
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }
}
