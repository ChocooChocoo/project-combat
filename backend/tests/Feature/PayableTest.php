<?php

namespace Tests\Feature;

use App\Models\SupplierPayment;
use App\Support\CurrentGym;
use App\Support\Procurement\Receiving;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-66: supplier payables are derived from receipts and append-only payments. */
class PayableTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
    }

    public function test_outstanding_is_received_goods_minus_payments_and_a_payment_is_cash_out_not_an_expense(): void
    {
        $received = $this->partlyReceive(4);

        $this->actingAs($this->approver)->getJson($this->url('finance/payables'))
            ->assertOk()
            ->assertJsonPath('payables.0.supplier_id', $this->supplier->id)
            ->assertJsonPath('payables.0.supplier_name', 'Manila Fight Gear Supply')
            ->assertJsonPath('payables.0.received', $received)
            ->assertJsonPath('payables.0.paid', '0.00')
            ->assertJsonPath('payables.0.outstanding', $received);

        $expensesBefore = $this->gym->expenses()->count();
        $this->assertSame($received, $this->gym->expenses()->sole()->amount);

        $payment = $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => 200,
            'method' => 'cash',
            'paid_on' => '2026-11-06',
            'reference' => 'CASH-001',
            'note' => 'First payment.',
        ])->assertCreated()
            ->assertJsonPath('payment.amount', '200.00')
            ->assertJsonPath('payment.method', 'cash')
            ->assertJsonPath('payment.paid_on', '2026-11-06')
            ->assertJsonPath('payment.recorded_by_name', 'Ava Cruz');

        $outstanding = number_format((float) $received - 200, 2, '.', '');

        $this->actingAs($this->approver)->getJson($this->url('finance/payables'))
            ->assertOk()
            ->assertJsonPath('payables.0.paid', '200.00')
            ->assertJsonPath('payables.0.outstanding', $outstanding);

        $this->actingAs($this->approver)->getJson($this->url("finance/payables/{$this->supplier->id}"))
            ->assertOk()
            ->assertJsonPath('supplier.id', $this->supplier->id)
            ->assertJsonPath('payments.0.id', $payment->json('payment.id'))
            ->assertJsonPath('payments.0.amount', '200.00')
            ->assertJsonPath('outstanding', $outstanding)
            ->assertJsonCount(1, 'orders');

        $this->assertSame($expensesBefore, $this->gym->expenses()->count());
        $this->assertSame((float) $received, (float) $this->gym->expenses()->sum('amount'));

        $this->actingAs($this->owner)
            ->getJson($this->url('finance/expenses?from=2026-11-06&to=2026-11-06'))
            ->assertOk()
            ->assertJsonPath('totals.0.amount', $received)
            ->assertJsonPath('supplier_payments.0.id', $payment->json('payment.id'))
            ->assertJsonPath('supplier_payments.0.amount', '200.00');
    }

    public function test_an_overpayment_is_refused_and_the_remaining_balance_can_be_paid(): void
    {
        $received = $this->partlyReceive(4);
        $outstanding = (float) $received;

        $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => $outstanding + 0.01,
            'method' => 'bank',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame(0, $this->supplier->payments()->count());

        $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => $outstanding,
            'method' => 'bank',
            'paid_on' => today()->toDateString(),
        ])->assertCreated();

        $this->actingAs($this->approver)->getJson($this->url('finance/payables'))
            ->assertOk()
            ->assertJsonPath('payables.0.outstanding', '0.00');

        $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => 1,
            'method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_a_correction_is_a_negative_linked_row_and_only_reverses_once(): void
    {
        $this->partlyReceive(4);

        $payment = $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => 300,
            'method' => 'cash',
        ])->assertCreated()->json('payment');

        $correction = $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => -100,
            'method' => 'cash',
            'corrects_id' => $payment['id'],
            'note' => 'Wrong amount keyed.',
        ])->assertCreated()
            ->assertJsonPath('payment.amount', '-100.00')
            ->assertJsonPath('payment.corrects_id', $payment['id'])
            ->json('payment');

        $this->assertSame(2, $this->supplier->payments()->count());
        $this->assertSame(1, $this->gym->expenses()->count());

        $this->actingAs($this->approver)->getJson($this->url("finance/payables/{$this->supplier->id}"))
            ->assertOk()
            ->assertJsonPath('paid', '200.00')
            ->assertJsonPath('outstanding', '160.00')
            ->assertJsonPath('payments.0.id', $correction['id'])
            ->assertJsonPath('payments.0.amount', '-100.00');

        $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => -100,
            'method' => 'cash',
            'corrects_id' => $payment['id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('corrects_id');

        $this->actingAs($this->approver)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => -500,
            'method' => 'cash',
            'corrects_id' => $payment['id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        app(CurrentGym::class)->set($this->gym);
        $model = $this->gym->supplierPayments()->findOrFail($payment['id']);

        try {
            $model->update(['note' => 'Changed']);
            $this->fail('A supplier payment was changed.');
        } catch (LogicException) {
        }

        try {
            $model->delete();
            $this->fail('A supplier payment was deleted.');
        } catch (LogicException) {
        }

        $this->assertSame(2, $this->supplier->payments()->count());
        $this->assertSame('cash', SupplierPayment::findOrFail($payment['id'])->method);
    }

    public function test_payables_need_the_finance_payables_permissions(): void
    {
        $this->partlyReceive(4);

        $this->actingAs($this->requester)->getJson($this->url('finance/payables'))->assertOk();
        $this->actingAs($this->requester)->postJson($this->url('finance/supplier-payments'), [
            'supplier_id' => $this->supplier->id,
            'amount' => 10,
            'method' => 'cash',
        ])->assertForbidden();

        $this->actingAs($this->approver)->getJson($this->url('finance/payables'))->assertOk();
    }

    /** @return string the receipt total */
    private function partlyReceive(int $quantity): string
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));

        [, $receipt] = app(Receiving::class)->receive(
            $this->gym,
            $order,
            [['purchase_order_line_id' => $order->lines->sole()->id, 'quantity' => $quantity]],
            $this->owner,
            today(),
        );

        return $receipt->total;
    }
}
