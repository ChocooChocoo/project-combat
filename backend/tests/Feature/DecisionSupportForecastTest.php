<?php

namespace Tests\Feature;

use App\Support\DecisionSupport\BusinessDashboards;
use App\Support\DecisionSupport\Period;
use App\Support\Finance\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportForecastTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    private function expense(string $amount, string $date, ?string $source = null): void
    {
        $this->gym->expenses()->create(['category' => 'other', 'amount' => $amount, 'incurred_on' => $date,
            'source_type' => $source, 'source_id' => $source ? 1 : null, 'recorded_by' => $this->owner->id]);
    }

    public function test_completed_history_linear_forecast_and_future_to_cap(): void
    {
        foreach ([['08', '100.00', '50.00'], ['09', '200.00', '100.00'], ['10', '300.00', '150.00']] as [$month, $incoming, $outgoing]) {
            $this->income('other', $incoming, "2026-{$month}-01 10:00:00");
            $this->expense($outgoing, "2026-{$month}-01");
        }
        $this->income('other', '99999.00', '2026-11-01 10:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('forecast').'?from=2026-11-01&to=2027-01-01')->assertOk()
            ->assertJsonPath('basis', 'income_expense')->assertJsonPath('method', 'least_squares')
            ->assertJsonPath('window.from', '2026-08-01')->assertJsonPath('window.to', '2026-10-31')
            ->assertJsonPath('window.months', 3)->assertJsonPath('window.excluded_partial_month', '2026-11')
            ->assertJsonPath('fit.income_r_squared', 1)->assertJsonPath('buckets.0.period', '2026-11')
            ->assertJsonPath('buckets.0.income', '400.00')->assertJsonPath('buckets.0.expenses', '200.00')
            ->assertJsonPath('buckets.2.period', '2027-01')->assertJsonPath('buckets.2.net', '300.00');
        $this->assertSame(app(Reports::class)->incomeAgainstExpenses($this->gym, '2026-08-01', '2026-10-31')['buckets'], $response->json('history'));
    }

    public function test_source_age_zero_gaps_and_old_zero_rows_control_minimum_history(): void
    {
        $this->income('other', '100.00', '2026-09-01 10:00:00');
        $url = $this->decisionUrl('forecast').'?from=2026-11-07&to=2026-11-07';
        $this->actingAs($this->owner)->getJson($url)->assertOk()->assertJsonPath('method', 'average')
            ->assertJsonPath('method_name', 'average, not enough history')->assertJsonPath('window.months', 2)
            ->assertJsonPath('history.1.income', '0.00')->assertJsonPath('buckets.0.income', '50.00')->assertJsonPath('fit.income_r_squared', null);
        $this->income('other', '0.00', '2024-01-01 10:00:00');
        $this->getJson($url)->assertOk()->assertJsonPath('method', 'least_squares')->assertJsonPath('window.months', 12)
            ->assertJsonPath('window.from', '2025-11-01')->assertJsonCount(12, 'history');
    }

    public function test_no_history_is_not_twelve_invented_zero_months_and_completed_month_end_rules(): void
    {
        $this->income('other', '100.00', '2026-10-01 10:00:00');
        foreach ([['2026-10-30', '2026-09-30', 'no_history', 0], ['2026-10-31', '2026-10-31', 'average', 1]] as [$to, $end, $method, $count]) {
            $this->actingAs($this->owner)->getJson($this->decisionUrl('forecast')."?from={$to}&to={$to}")->assertOk()
                ->assertJsonPath('window.to', $end)->assertJsonPath('method', $method)->assertJsonCount($count, 'history');
        }
        $this->travelTo(Carbon::parse('2026-11-30 23:59:59', 'Asia/Manila'));
        $this->actingAs($this->owner)->getJson($this->decisionUrl('forecast').'?from=2026-11-30&to=2026-11-30')->assertOk()
            ->assertJsonPath('window.to', '2026-10-31')->assertJsonPath('window.excluded_partial_month', '2026-11');
        $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'Asia/Manila'));
        $this->getJson($this->decisionUrl('forecast').'?from=2026-11-30&to=2026-11-30')->assertOk()
            ->assertJsonPath('window.to', '2026-11-30')->assertJsonPath('window.excluded_partial_month', null);
        $this->getJson($this->decisionUrl('forecast').'?from=2000-01-01&to=2000-01-01')->assertOk()
            ->assertJsonPath('method', 'no_history')->assertJsonPath('window.from', null)->assertJsonPath('buckets', [])
            ->assertJsonPath('totals.net', '0.00')->assertJsonPath('meta.has_data', false);
    }

    public function test_cash_flow_uses_supplier_cash_not_goods_receipt_accrual_and_ignores_receipt_prehistory(): void
    {
        $this->income('other', '400.00', '2026-10-01 10:00:00');
        $this->expense('50.00', '2026-10-01');
        $this->expense('30.00', '2026-10-01', 'payroll_run');
        $this->expense('1000.00', '2026-08-01', 'goods_receipt');
        $supplier = $this->gym->suppliers()->create(['name' => 'Forecast supplier']);
        $this->gym->supplierPayments()->create(['supplier_id' => $supplier->id, 'amount' => '200.00', 'method' => 'bank',
            'paid_on' => '2026-10-01', 'recorded_by' => $this->owner->id]);
        $url = $this->decisionUrl('forecast').'?from=2026-11-01&to=2026-11-07';
        $this->actingAs($this->owner)->getJson($url.'&basis=cash_flow')->assertOk()->assertJsonPath('basis', 'cash_flow')
            ->assertJsonPath('window.from', '2026-10-01')->assertJsonPath('window.months', 1)
            ->assertJsonPath('history.0.income', '400.00')->assertJsonPath('history.0.expenses', '280.00')
            ->assertJsonPath('buckets.0.net', '120.00');
        $this->getJson($url)->assertOk()->assertJsonPath('window.from', '2026-08-01')->assertJsonPath('window.months', 3)
            ->assertJsonPath('history.0.expenses', '1000.00')->assertJsonPath('history.2.expenses', '80.00');
        $this->getJson($url.'&basis=invented')->assertUnprocessable()->assertJsonValidationErrors('basis');
    }

    public function test_monthly_adapter_folds_daily_finance_buckets_and_preserves_clipped_totals(): void
    {
        foreach ([['2026-11-14', '100.00'], ['2026-11-15', '25.00'], ['2026-12-15', '50.00'], ['2026-12-16', '100.00']] as [$day, $amount]) $this->income('other', $amount, $day.' 10:00:00');
        $this->expense('10.00', '2026-12-01');
        $period = new Period('2026-11-15', '2026-12-15');
        $actual = (new BusinessDashboards)->monthlyFinance($this->gym, $period);
        $this->assertSame(['2026-11', '2026-12'], array_column($actual['buckets'], 'period'));
        $this->assertSame('25.00', $actual['buckets'][0]['income']);
        $this->assertSame('40.00', $actual['buckets'][1]['net']);
        $this->assertSame(app(Reports::class)->incomeAgainstExpenses($this->gym, $period->from(), $period->to())['totals'], $actual['totals']);
        $cash = (new BusinessDashboards)->monthlyFinance($this->gym, $period, 'cash_flow');
        $this->assertSame('50.00', $cash['buckets'][1]['in']);
        $this->assertSame(app(Reports::class)->cashFlow($this->gym, $period->from(), $period->to())['totals'], $cash['totals']);
    }

    public function test_refund_history_and_final_completed_day_are_retained_before_projection_clamp(): void
    {
        $this->income('other', '100.00', '2026-09-01 10:00:00');
        $this->income('other', '-150.00', '2026-10-31 23:59:59.999999');
        $this->income('other', '999.00', '2026-11-01 00:00:00');
        $this->actingAs($this->owner)->getJson($this->decisionUrl('forecast').'?from=2026-11-07&to=2026-11-07')->assertOk()
            ->assertJsonPath('window.months', 2)->assertJsonPath('method', 'average')
            ->assertJsonPath('history.0.income', '100.00')->assertJsonPath('history.1.income', '-150.00')
            ->assertJsonPath('history.1.net', '-150.00')->assertJsonPath('buckets.0.income', '0.00');
    }
}
