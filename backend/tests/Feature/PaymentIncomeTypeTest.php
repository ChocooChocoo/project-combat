<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Membership;
use App\Models\Payment;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

/** R-63: every payment carries the income type it belongs to. */
class PaymentIncomeTypeTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    private function paymentFor(array $where): Payment
    {
        return Payment::withoutGlobalScope('gym')->where($where)->latest('id')->firstOrFail();
    }

    private function activeMembership(): Membership
    {
        return Membership::withoutGlobalScope('gym')->firstOrFail();
    }

    public function test_membership_and_session_payments_get_their_type(): void
    {
        $this->assertSame('membership', $this->paymentFor(['membership_id' => $this->activeMembership()->id])->income_type);

        $this->bookingPayment()->assertCreated();

        $this->assertSame('session', $this->paymentFor(['session_booking_id' => $this->bookingId])->income_type);
    }

    public function test_private_coaching_gets_its_own_type(): void
    {
        $session = $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData([
            'type' => 'private_coaching',
            'name' => 'Private session',
            'starts_at' => '2026-11-10 10:00:00',
            'ends_at' => '2026-11-10 11:00:00',
            'fee' => 500,
        ]))->assertCreated()->json('session.id');

        $booking = $this->actingAs($this->applicant)
            ->postJson($this->url("timetable/{$session}/book"))->assertCreated()->json('booking.id');

        $this->actingAs($this->owner)->postJson($this->url('payments'), [
            'session_booking_id' => $booking, 'amount' => 500, 'method' => 'cash',
        ])->assertCreated();

        $this->assertSame('private_coaching', $this->paymentFor(['session_booking_id' => $booking])->income_type);
    }

    public function test_a_walk_in_needs_no_account_and_a_correction_keeps_its_type(): void
    {
        app(CurrentGym::class)->set($this->gym);

        $payment = Payment::create([
            'user_id' => null,
            'amount' => 50,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'income_type' => 'walk_in',
            'payer_name' => 'Walk-in guest',
        ]);

        $this->assertNull($payment->user_id);
        $this->assertSame('walk_in', $payment->income_type);

        $correction = Payment::create([
            'user_id' => null,
            'amount' => -50,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'corrects_id' => $payment->id,
            'note' => 'Wrong amount',
        ]);

        $this->assertSame('walk_in', $correction->income_type);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        app(CurrentGym::class)->set($this->gym);

        $this->expectException(LogicException::class);

        Payment::create([
            'user_id' => null,
            'amount' => 10,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
            'income_type' => 'mystery',
        ]);
    }

    public function test_a_payment_with_no_link_and_no_type_is_refused(): void
    {
        app(CurrentGym::class)->set($this->gym);

        $this->expectException(LogicException::class);

        Payment::create([
            'user_id' => null,
            'amount' => 10,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ]);
    }
}
