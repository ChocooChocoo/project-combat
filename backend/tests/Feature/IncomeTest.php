<?php

namespace Tests\Feature;

use App\Http\Controllers\MyBookingController;
use App\Http\Controllers\MyMembershipController;
use App\Models\Payment;
use App\Support\BookingLedger;
use App\Support\CurrentGym;
use App\Support\MembershipLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-63: every payment appears as income with the type its source gives it. */
class IncomeTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();
    }

    public function test_membership_session_and_private_coaching_get_their_own_types(): void
    {
        $membership = $this->makeMembership($this->member);
        $this->payMembership($membership, '1000.00');

        $session = $this->makeSession('classes', '150.00');
        $booking = $this->makeBooking($this->member, $session, 'booked', '150.00', $membership);
        $this->payBooking($booking, '150.00');

        $private = $this->makeSession('private_coaching', '500.00', 'Private pad work');
        $privateBooking = $this->makeBooking($this->member, $private, 'requested', '500.00', $membership);
        $this->payBooking($privateBooking, '500.00');

        $response = $this->actingAs($this->owner)->getJson($this->url('finance/income'))->assertOk();

        $this->assertSame(
            ['membership', 'private_coaching', 'session'],
            collect($response->json('income'))->pluck('type')->sort()->values()->all(),
        );
        $this->assertSame(
            ['Mia Torres'],
            collect($response->json('income'))->pluck('member_name')->unique()->values()->all(),
        );
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(1650.0, collect($response->json('totals'))->sum(fn (array $row) => (float) $row['amount']));
        $this->assertSame(
            ['membership' => 1000.0, 'private_coaching' => 500.0, 'session' => 150.0],
            collect($response->json('totals'))->mapWithKeys(fn (array $row) => [$row['type'] => (float) $row['amount']])->all(),
        );
    }

    public function test_paymongo_checkout_confirmations_land_with_the_same_types(): void
    {
        $membership = $this->makeMembership($this->member, ['status' => 'awaiting_payment']);

        MyMembershipController::recordCheckout($membership, 'cs_member', [
            'amount' => 1000.0, 'payment_id' => 'pay_member',
        ], app(MembershipLedger::class));

        $session = $this->makeSession('classes', '150.00');
        $booking = $this->makeBooking($this->member, $session, 'booked', '150.00', $membership);

        MyBookingController::recordCheckout($booking, 'cs_booking', [
            'amount' => 150.0, 'payment_id' => 'pay_booking',
        ], app(BookingLedger::class));

        $rows = collect($this->actingAs($this->owner)
            ->getJson($this->url('finance/income'))->assertOk()->json('income'));

        $this->assertSame(['membership', 'session'], $rows->pluck('type')->sort()->values()->all());
        $this->assertSame(['paymongo'], $rows->pluck('method')->unique()->values()->all());
    }

    public function test_a_correction_keeps_the_original_type(): void
    {
        $membership = $this->makeMembership($this->member);
        $payment = $this->payMembership($membership, '1000.00');

        $this->actingAs($this->owner)
            ->postJson($this->url("payments/{$payment->id}/correct"), ['reason' => 'Wrong member'])
            ->assertCreated();

        $correction = collect($this->actingAs($this->owner)
            ->getJson($this->url('finance/income'))->assertOk()->json('income'))
            ->firstWhere('corrects_id', $payment->id);

        $this->assertNotNull($correction);
        $this->assertSame('membership', $correction['type']);
        $this->assertSame('-1000.00', $correction['amount']);
    }

    public function test_income_filters_by_type_and_date_and_totals_match_the_filtered_rows(): void
    {
        $membership = $this->makeMembership($this->member, ['amount_charged' => 2000]);
        $this->payMembership($membership, '1000.00', paidAt: Carbon::parse('2026-10-31 10:00:00'));
        $this->payMembership($membership, '400.00', paidAt: Carbon::parse('2026-11-02 10:00:00'));

        $session = $this->makeSession('classes', '150.00');
        $booking = $this->makeBooking($this->member, $session, 'booked', '150.00', $membership);
        $this->payBooking($booking, '150.00', paidAt: Carbon::parse('2026-11-03 10:00:00'));

        $all = $this->actingAs($this->owner)
            ->getJson($this->url('finance/income?from=2026-11-01&to=2026-11-30&type=all'))
            ->assertOk();
        $this->assertSame(2, $all->json('meta.total'));
        $this->assertSame(25, $all->json('meta.per_page'));
        $this->assertSame(550.0, collect($all->json('income'))->sum(fn (array $row) => (float) $row['amount']));
        $this->assertSame(550.0, collect($all->json('totals'))->sum(fn (array $row) => (float) $row['amount']));

        $memberships = $this->actingAs($this->owner)
            ->getJson($this->url('finance/income?from=2026-11-01&to=2026-11-30&type=membership'))
            ->assertOk();
        $this->assertSame(1, $memberships->json('meta.total'));
        $this->assertSame(['type' => 'membership', 'amount' => '400.00'], $memberships->json('totals.0'));

        $sessions = $this->actingAs($this->owner)
            ->getJson($this->url('finance/income?from=2026-11-01&to=2026-11-30&type=session'))
            ->assertOk();
        $this->assertSame(1, $sessions->json('meta.total'));

        $this->actingAs($this->owner)
            ->getJson($this->url('finance/income?from=2026-12-01&to=2026-12-31'))
            ->assertOk()->assertJsonCount(0, 'income')->assertJsonPath('meta.total', 0);
    }

    public function test_income_pages_by_25(): void
    {
        app(CurrentGym::class)->set($this->gym);
        foreach (range(1, 26) as $index) {
            Payment::create([
                'user_id' => null,
                'amount' => 10,
                'method' => 'cash',
                'paid_at' => now()->subMinutes($index),
                'income_type' => 'walk_in',
                'payer_name' => "Guest {$index}",
            ]);
        }

        $page = $this->actingAs($this->owner)
            ->getJson($this->url('finance/income?type=walk_in'))->assertOk();

        $this->assertCount(25, $page->json('income'));
        $this->assertSame(26, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));
    }

    public function test_income_needs_finance_payments_view(): void
    {
        $viewer = $this->employeeWithFinanceRole(['finance.payments.view']);
        $plain = $this->employeeWithFinanceRole([]);

        $this->actingAs($viewer)->getJson($this->url('finance/income'))->assertOk();
        $this->actingAs($plain)->getJson($this->url('finance/income'))->assertForbidden();
    }
}
