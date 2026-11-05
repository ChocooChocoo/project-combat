<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
        config(['services.paymongo.secret_key' => 'sk_test_x', 'services.paymongo.webhook_secret' => 'whsk_test']);
    }

    public function test_fee_is_owed_until_paid_and_correction_reopens_balance(): void
    {
        $this->bookingPayload()->assertJsonPath('upcoming.0.payment_state', 'owed')->assertJsonPath('upcoming.0.balance', '150.00');
        $payment = $this->bookingPayment()->assertCreated()->assertJsonPath('payment.session_booking_id', $this->bookingId)->json('payment.id');
        $this->bookingPayload()->assertJsonPath('upcoming.0.payment_state', 'paid')->assertJsonPath('upcoming.0.balance', '0.00');
        $this->actingAs($this->owner)->getJson($this->url('payments'))->assertOk()->assertJsonPath('payments.0.session.name', 'Boxing');
        $this->postJson($this->url("payments/{$payment}/correct"), ['reason' => 'Wrong entry'])->assertCreated()->assertJsonPath('payment.corrects_id', $payment);
        $this->bookingPayload()->assertJsonPath('upcoming.0.balance', '150.00')->assertJsonPath('upcoming.0.payment_state', 'owed');
        $this->assertTrue($this->applicant->notifications()->where('data->type', 'payment')->exists());
    }

    public function test_payment_validation_and_owed_picker_permissions(): void
    {
        $this->bookingPayment(['amount' => 151])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->bookingPayment(['membership_id' => $this->membership(1)->id])->assertUnprocessable();
        $this->actingAs($this->owner)->getJson($this->url('payments/owed-bookings'))->assertOk()->assertJsonCount(1, 'bookings');
        $this->actingAs($this->coach)->getJson($this->url('payments/owed-bookings'))->assertForbidden();
        foreach (['requested', 'waitlisted', 'cancelled', 'rejected'] as $status) {
            SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['status' => $status])->save();
            $this->bookingPayment()->assertStatus(409);
        }
    }

    public function test_included_booking_has_included_state(): void
    {
        SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['fee' => 0])->save();
        $this->bookingPayload()->assertJsonPath('upcoming.0.payment_state', 'included');
    }

    public function test_checkout_rejects_non_booked_and_another_users_confirmation(): void
    {
        $this->actingAs(User::factory()->create())->postJson("/api/my-bookings/{$this->bookingId}/checkout/confirm")->assertNotFound();
        SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['status' => 'waitlisted'])->save();
        $this->actingAs($this->applicant)->postJson("/api/my-bookings/{$this->bookingId}/checkout")->assertStatus(409);
    }

    public function test_paid_open_checkout_is_committed_even_when_nothing_more_is_owed(): void
    {
        SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['pending_checkout_id' => 'cs_paid'])->save();
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_paid' => Http::response(['data' => ['id' => 'cs_paid', 'attributes' => [
            'status' => 'active', 'payments' => [['id' => 'pay_paid', 'attributes' => ['status' => 'paid', 'amount' => 15000]]],
        ]]])]);
        $this->actingAs($this->applicant)->postJson("/api/my-bookings/{$this->bookingId}/checkout")->assertUnprocessable();
        $this->bookingPayload()->assertJsonPath('upcoming.0.payment_state', 'paid');
        $this->assertDatabaseHas('payments', ['session_booking_id' => $this->bookingId, 'paymongo_checkout_id' => 'cs_paid']);
    }

    public function test_payment_confirmed_after_session_cancellation_is_refunded_without_api_refund(): void
    {
        SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['pending_checkout_id' => 'cs_late'])->save();
        $this->actingAs($this->owner)->postJson($this->url("sessions/{$this->sessionId}/cancel"), ['reason' => 'Closed'])->assertOk();
        Http::fake(['api.paymongo.com/v1/checkout_sessions/cs_late' => Http::response(['data' => ['id' => 'cs_late', 'attributes' => [
            'status' => 'active', 'payments' => [['id' => 'pay_late', 'attributes' => ['status' => 'paid', 'amount' => 15000]]],
        ]]])]);
        $this->actingAs($this->applicant)->postJson("/api/my-bookings/{$this->bookingId}/checkout/confirm")->assertOk()
            ->assertJsonPath('booking.paid', '0.00')->assertJsonPath('booking.payment_state', 'refunded');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        $this->assertDatabaseHas('payments', ['session_booking_id' => $this->bookingId, 'amount' => -150, 'method' => 'paymongo']);
    }

    public function test_checkout_reuses_open_session_and_signed_webhook_records_once(): void
    {
        $paid = false;
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_booking', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_booking', 'status' => 'active']]]),
            'api.paymongo.com/v1/checkout_sessions/cs_booking' => function () use (&$paid) {
                return Http::response(['data' => ['id' => 'cs_booking', 'attributes' => ['status' => 'active', 'checkout_url' => 'https://checkout.paymongo.com/cs_booking', 'payments' => $paid ? [['id' => 'pay_booking', 'attributes' => ['status' => 'paid', 'amount' => 15000]]] : []]]]);
            },
        ]);
        $url = "/api/my-bookings/{$this->bookingId}/checkout";
        $this->actingAs(User::factory()->create())->postJson($url)->assertNotFound();
        $this->actingAs($this->applicant)->postJson($url)->assertOk()->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_booking');
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['data']['attributes']['line_items'][0]['amount'] === 15000);
        $this->postJson($url)->assertOk();
        Http::assertSentCount(2);
        $this->postJson("{$url}/confirm")->assertJsonPath('paid', false);
        $paid = true;
        $body = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => ['id' => 'cs_booking']]]]);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$body}", 'whsk_test');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$time},te={$signature},li="], $body)->assertOk();
        }
        $this->postJson("{$url}/confirm")->assertOk()->assertJsonPath('paid', true)->assertJsonPath('booking.payment_state', 'paid');
        $this->assertSame(1, Payment::withoutGlobalScope('gym')->where('session_booking_id', $this->bookingId)->count());
        $this->postJson($url)->assertUnprocessable();
    }
}
