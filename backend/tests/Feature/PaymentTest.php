<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Payment;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-34 and R-35: desk payments with receipts, corrections, and PayMongo online payments. */
class PaymentTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
        config(['services.paymongo.secret_key' => 'sk_test_x', 'services.paymongo.webhook_secret' => 'whsk_test']);
    }

    public function test_desk_payment_gets_a_receipt_and_activates_an_approved_membership(): void
    {
        $membership = $this->approved();
        $desk = $this->employeeWith(['finance' => 'manage']);

        $this->actingAs($desk)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1000, 'method' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('payment.method', 'cash')
            ->assertJsonPath('payment.received_by', trim("{$desk->first_name} {$desk->last_name}"));
        $this->assertSame(MembershipStatus::AwaitingPayment, $this->membership($membership->id)->status);

        $receipt = $this->actingAs($desk)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 500, 'method' => 'ewallet'])
            ->assertCreated()->json('payment.receipt_number');
        $this->assertMatchesRegularExpression('/^OR-\d+-\d{6}$/', $receipt);

        $active = $this->membership($membership->id);
        $this->assertSame(MembershipStatus::Active, $active->status);
        $this->assertSame(today()->addMonthNoOverflow()->subDay()->toDateString(), $active->ends_on->toDateString());

        $this->actingAs($this->applicant)->getJson("/api/my-memberships/{$membership->id}")
            ->assertJsonPath('membership.balance', '0.00')
            ->assertJsonCount(2, 'membership.payments')
            ->assertJsonPath('membership.payments.0.receipt_number', $receipt);
    }

    public function test_payment_never_approves_and_needs_finance_permission(): void
    {
        $membership = $this->apply();

        $this->actingAs($this->owner)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1500, 'method' => 'cash'])
            ->assertStatus(409);
        $this->assertSame(MembershipStatus::Applied, $this->membership($membership->id)->status);

        $viewer = $this->employeeWith(['finance' => 'view']);
        $this->actingAs($viewer)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1, 'method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($viewer)->getJson($this->url('payments'))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1, 'method' => 'paymongo'])
            ->assertJsonValidationErrors(['method']);
    }

    public function test_payments_cannot_be_changed_and_corrections_are_their_own_entries(): void
    {
        $membership = $this->active();
        app(CurrentGym::class)->set($this->gym);
        $payment = Payment::firstOrFail();

        try {
            $payment->update(['amount' => 1]);
            $this->fail('A payment was changed.');
        } catch (LogicException) {
        }

        $this->actingAs($this->owner)->postJson($this->url("payments/{$payment->id}/correct"), [])
            ->assertJsonValidationErrors(['reason']);
        $this->actingAs($this->owner)->postJson($this->url("payments/{$payment->id}/correct"), ['reason' => 'Wrong member'])
            ->assertCreated()
            ->assertJsonPath('payment.amount', '-1500.00')
            ->assertJsonPath('payment.corrects_id', $payment->id);
        $this->actingAs($this->owner)->postJson($this->url("payments/{$payment->id}/correct"), ['reason' => 'Again'])
            ->assertStatus(409);

        $this->actingAs($this->applicant)->getJson("/api/my-memberships/{$membership->id}")
            ->assertJsonPath('membership.balance', '1500.00')
            ->assertJsonCount(2, 'membership.payments');
    }

    public function test_online_checkout_is_recorded_only_after_paymongo_confirms_and_only_once(): void
    {
        $membership = $this->approved();
        $state = ['paid' => false];

        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => [
                'id' => 'cs_test_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_1', 'status' => 'active'],
            ]]),
            'api.paymongo.com/v1/checkout_sessions/cs_test_1' => function () use (&$state) {
                return Http::response(['data' => ['id' => 'cs_test_1', 'attributes' => [
                    'status' => 'active',
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_1',
                    'payments' => $state['paid'] ? [['id' => 'pay_1', 'attributes' => ['status' => 'paid', 'amount' => 150000]]] : [],
                ]]]);
            },
        ]);

        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout")
            ->assertOk()->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_test_1');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $r['data']['attributes']['line_items'][0]['amount'] === 150000);

        // Asking again while unpaid reuses the same checkout instead of making a second one.
        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout")
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_test_1');
        Http::assertSentCount(2);

        // Coming back before paying records nothing.
        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout/confirm")
            ->assertJsonPath('paid', false);

        $state['paid'] = true;
        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout/confirm")
            ->assertJsonPath('paid', true)
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.payments.0.method', 'paymongo');

        // A late webhook for the same checkout does not record it twice.
        $this->webhook('cs_test_1')->assertOk();
        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout/confirm")->assertOk();
        app(CurrentGym::class)->set($this->gym);
        $this->assertSame(1, Payment::count());

        // Nothing left to pay: no new checkout.
        $this->actingAs($this->applicant)->postJson("/api/my-memberships/{$membership->id}/checkout")
            ->assertJsonValidationErrors(['balance']);
    }

    public function test_signed_webhook_records_and_fake_ones_are_rejected(): void
    {
        $membership = $this->approved();
        $membership->forceFill(['pending_checkout_id' => 'cs_test_2'])->save();

        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_test_2' => Http::response(['data' => ['id' => 'cs_test_2', 'attributes' => [
            'status' => 'active',
            'payments' => [['id' => 'pay_2', 'attributes' => ['status' => 'paid', 'amount' => 150000]]],
        ]]])]);

        $this->webhook('cs_test_2', 'wrong-secret')->assertUnauthorized();
        $this->webhook('cs_test_2', 'whsk_test', time() - 3600)->assertUnauthorized();
        $this->postJson('/api/webhooks/paymongo', $this->event('cs_test_2'))->assertUnauthorized();
        $this->assertSame(MembershipStatus::AwaitingPayment, $this->membership($membership->id)->status);

        $this->webhook('cs_test_2')->assertOk();
        $this->assertSame(MembershipStatus::Active, $this->membership($membership->id)->status);
    }

    public function test_a_signed_webhook_still_needs_paymongo_to_say_paid(): void
    {
        $membership = $this->approved();
        $membership->forceFill(['pending_checkout_id' => 'cs_test_3'])->save();
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_test_3' => Http::response(['data' => ['id' => 'cs_test_3', 'attributes' => [
            'status' => 'active', 'payments' => [],
        ]]])]);

        $this->webhook('cs_test_3')->assertOk();
        $this->assertSame(MembershipStatus::AwaitingPayment, $this->membership($membership->id)->status);
    }

    private function event(string $checkoutId): array
    {
        return ['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => ['id' => $checkoutId]]]];
    }

    private function webhook(string $checkoutId, string $secret = 'whsk_test', ?int $time = null)
    {
        $body = json_encode($this->event($checkoutId));
        $time ??= time();
        $signature = hash_hmac('sha256', "{$time}.{$body}", $secret);

        return $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$time},te={$signature},li=",
        ], $body);
    }
}
