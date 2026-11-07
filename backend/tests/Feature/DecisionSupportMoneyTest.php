<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymService;
use App\Support\Finance\Receivables;
use App\Support\Finance\Reports;
use App\Support\IncomeQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportMoneyTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_signed_revenue_and_service_allocation_match_finance_helpers(): void
    {
        $membership = $this->makeMembership($this->member);
        $this->income('membership', '1000.00', attributes: ['membership_id' => $membership->id]);
        $service = GymService::factory()->forGym($this->gym)->create(['name' => 'Recorded service']);
        $session = $this->occurrence(attributes: ['gym_service_id' => $service->id, 'status' => 'cancelled']);
        $booking = $this->makeBooking($this->member, $session, 'cancelled', '500.00');
        $original = $this->income('session', '500.00', attributes: ['session_booking_id' => $booking->id]);
        $this->income('session', '-100.00', attributes: ['session_booking_id' => $booking->id, 'corrects_id' => $original->id]);
        $this->income('merchandise', '200.00');
        $url = $this->decisionUrl('money').'?from=2026-11-02&to=2026-11-02';
        $response = $this->actingAs($this->owner)->getJson($url)->assertOk()
            ->assertJsonPath('income_expense.totals.income', '1600.00')->assertJsonPath('session_services.0.amount', '400.00');
        $this->assertSame(app(Reports::class)->incomeAgainstExpenses($this->gym, '2026-11-02', '2026-11-02'), $response->json('income_expense'));
        $this->assertSame(app(Reports::class)->cashFlow($this->gym, '2026-11-02', '2026-11-02'), $response->json('cash_flow'));
        $this->assertSame(array_keys(config('finance.income_types')), array_column($response->json('income_types'), 'type'));

        $unassigned = $this->makeBooking($this->member, $this->occurrence(), 'booked', '75.00');
        $this->income('session', '75.00', attributes: ['session_booking_id' => $unassigned->id]);
        $private = $this->makeBooking($this->member, $this->occurrence(attributes: ['type' => 'private_coaching', 'gym_service_id' => $service->id]), 'booked', '250.00');
        $this->income('private_coaching', '250.00', attributes: ['session_booking_id' => $private->id]);
        $this->income('session', '50.00', attributes: ['membership_id' => $membership->id, 'session_booking_id' => $booking->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('income_expense.totals.income', '1975.00')->assertJsonPath('service_allocation_anomalies', 1);
        $rows = app(IncomeQuery::class)->serviceTotals($this->gym, '2026-11-02', '2026-11-02');
        $this->assertSame('650.00', $rows->firstWhere('service_id', $service->id)['amount']);
        $this->assertSame('75.00', $rows->firstWhere('service_id', null)['amount']);
    }

    public function test_receivables_snapshot_stays_current_and_sums_positive_sources_only(): void
    {
        $overpaid = $this->makeMembership($this->member, ['amount_charged' => 500]);
        $this->payMembership($overpaid, '800.00');
        $debt = $this->makeMembership($this->member, ['amount_charged' => 1000, 'status' => 'cancelled']);
        $paid = $this->payMembership($debt, '300.00');
        $this->income('membership', '-100.00', attributes: ['membership_id' => $debt->id, 'corrects_id' => $paid->id]);
        $booking = $this->makeBooking($this->member, $this->occurrence(), 'requested', '150.00');
        $this->payBooking($booking, '50.00');
        foreach ([['2020-01-01', '2020-01-01'], ['2026-11-01', '2026-11-07']] as [$from, $to]) {
            $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('money')."?from={$from}&to={$to}")->assertOk()
                ->assertJsonPath('receivables.outstanding', '900.00')->assertJsonPath('receivables.debtor_count', 1)
                ->assertJsonPath('receivables.basis', 'current_snapshot')->assertJsonPath('receivables.as_of', '2026-11-07')
                ->assertJsonPath('receivables.historical_available', false);
            $this->assertSame(app(Receivables::class)->forGym($this->gym)->map(fn ($row) => collect($row)->except('sources')->all())->all(), $response->json('receivables.members'));
        }
    }

    public function test_receivable_payment_subqueries_ignore_corrupt_cross_gym_payments(): void
    {
        $membership = $this->makeMembership($this->member);
        $booking = $this->makeBooking($this->member, $this->occurrence(), 'booked', '150.00');
        $other = Gym::factory()->approved()->create();
        foreach ([['membership_id' => $membership->id], ['session_booking_id' => $booking->id]] as $link) {
            DB::table('payments')->insert([...$link, 'gym_id' => $other->id, 'user_id' => $this->member->id,
                'amount' => 10000, 'income_type' => 'session', 'method' => 'cash', 'paid_at' => '2026-11-02 10:00:00']);
        }
        $rows = app(Receivables::class)->forGym($this->gym);
        $this->assertCount(1, $rows);
        $this->assertSame('1150.00', $rows->first()['total']);
        $this->assertSame('1150.00', app(Receivables::class)->forUser($this->member)['balance']);
    }

    public function test_signed_mix_and_half_open_final_day_income_filter(): void
    {
        $this->income('membership', '1000.00', '2026-11-02 23:59:59.999999');
        $this->income('merchandise', '-200.00', '2026-11-02 23:59:59');
        $this->income('membership', '999.00', '2026-11-03 00:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('money').'?from=2026-11-02&to=2026-11-02')->assertOk()
            ->assertJsonPath('income_expense.totals.income', '800.00');
        $mix = collect($response->json('income_types'))->keyBy('type');
        $this->assertSame(125, $mix['membership']['share']);
        $this->assertSame(-25, $mix['merchandise']['share']);
        $this->assertSame(2, app(IncomeQuery::class)->forGym($this->gym, '2026-11-02', '2026-11-02')->count());
        $this->getJson($this->decisionUrl('money').'?from=2000-01-01&to=2000-01-01')->assertOk()
            ->assertJsonPath('meta.has_data', false)->assertJsonPath('income_expense.totals.income', '0.00')->assertJsonPath('income_types.0.share', null);
        $this->income('other', '-900.00');
        $this->getJson($this->decisionUrl('money').'?from=2026-11-02&to=2026-11-02')->assertOk()
            ->assertJsonPath('income_expense.totals.income', '-100.00')->assertJsonPath('income_types.0.share', null)
            ->assertJsonPath('income_types.4.share', null);
    }
}
