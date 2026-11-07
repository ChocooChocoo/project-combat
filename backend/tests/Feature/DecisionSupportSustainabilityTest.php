<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Support\DecisionSupport\BusinessDashboards;
use App\Support\DecisionSupport\Forecast;
use App\Support\DecisionSupport\MembershipMetrics;
use App\Support\DecisionSupport\Period;
use App\Support\DecisionSupport\TrainingDashboards;
use App\Support\Finance\BudgetActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportSustainabilityTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
        $this->travelTo(now()->setDate(2027, 1, 10));
    }

    private function expense(string $amount, string $date, ?string $source = null): void
    {
        Expense::create(['category' => 'utilities', 'amount' => $amount, 'incurred_on' => $date,
            'description' => 'Sustainability fixture', 'recorded_by' => $this->owner->id, 'source_type' => $source]);
    }

    public function test_all_five_measures_reconcile_with_standalone_services_for_clipped_dates(): void
    {
        $membership = $this->makeMembership($this->member);
        $this->membershipEvent($membership, 'activated', '2026-10-01 00:00:00');
        $this->membershipEvent($membership, 'renewed', '2026-12-10 00:00:00');
        $session = $this->occurrence('2026-11-15 23:59:59', ['capacity' => 4]);
        $this->markedBooking($session, 'present');
        $this->markedBooking($session, null, 'requested');
        foreach ([['100.00', '2026-11-20'], ['200.00', '2026-12-15 23:59:59.999999'], ['450.00', '2026-12-16 00:00:00'], ['900.00', '2026-11-14']] as [$amount, $date]) $this->income('membership', $amount, $date);
        $this->expense('50.00', '2026-11-20');
        $this->expense('200.00', '2026-12-15');
        $this->expense('500.00', '2026-12-20');
        $period = new Period('2026-11-15', '2026-12-15');
        $data = $this->actingAs($this->owner)->getJson($this->decisionUrl('sustainability').'?from=2026-11-15&to=2026-12-15')
            ->assertOk()->assertJsonPath('meta.has_data', true)->json();
        $business = new BusinessDashboards;
        $money = $business->money($this->gym, $period);
        $use = (new TrainingDashboards)->services($this->gym, $period);
        $this->assertSame($business->monthlyFinance($this->gym, $period), $data['financial']);
        $this->assertSame(['income' => '300.00', 'expenses' => '250.00', 'net' => '50.00'], $data['financial']['totals']);
        $this->assertSame(['2026-11', '2026-12'], array_column($data['financial']['buckets'], 'period'));
        $this->assertEquals($money['income_types'], $data['revenue_mix']['income_types']);
        $this->assertSame($money['session_services'], $data['revenue_mix']['session_services']);
        $this->assertSame($money['service_allocation_anomalies'], $data['revenue_mix']['service_allocation_anomalies']);
        $this->assertEquals((new MembershipMetrics)->forPeriod($this->gym, $period)['summary'], $data['membership']);
        $this->assertEquals($use['classes'], $data['utilization']['classes']);
        $this->assertEquals($use['rooms'], $data['utilization']['rooms']);
        $this->assertSame(50, $data['utilization']['classes'][0]['fill_rate']);
        $cash = $data['cash_flow_forecast'];
        unset($cash['meta']);
        $this->assertEquals((new Forecast)->forPeriod($this->gym, $period, 'cash_flow'), $cash);
        $this->assertSame('cash_flow', $cash['basis']);
        $this->assertSame('Cash in', $cash['series_labels']['income']);
    }

    public function test_budget_uses_the_whole_end_month_with_numeric_rates_without_changing_legacy_output(): void
    {
        DB::table('budgets')->insert([
            ['gym_id' => $this->gym->id, 'month' => '2026-12-01', 'kind' => 'income', 'category' => 'membership', 'amount' => '1000.00', 'updated_by' => $this->owner->id],
            ['gym_id' => $this->gym->id, 'month' => '2026-12-01', 'kind' => 'expense', 'category' => 'utilities', 'amount' => '500.00', 'updated_by' => $this->owner->id],
        ]);
        $this->income('membership', '200.00', '2026-12-10');
        $this->income('membership', '450.00', '2026-12-20');
        $this->expense('200.00', '2026-12-10');
        $this->expense('500.00', '2026-12-20');
        $legacy = (new BudgetActual)->forMonth($this->gym, '2026-12');
        $data = $this->actingAs($this->owner)->getJson($this->decisionUrl('sustainability').'?from=2026-11-15&to=2026-12-15')
            ->assertOk()->assertJsonPath('budget.month', '2026-12')->assertJsonPath('budget.basis', 'whole_month_containing_range_end')->json();
        $this->assertSame([
            ['kind' => 'income', 'budget' => '1000.00', 'actual' => '650.00', 'difference' => '350.00', 'percentage' => 65],
            ['kind' => 'expense', 'budget' => '500.00', 'actual' => '700.00', 'difference' => '-200.00', 'percentage' => 140],
        ], $data['budget']['totals']);
        foreach ($legacy['budgets'] as $index => $row) {
            $expected = [...$row, 'percentage' => $row['percentage'] === null ? null : (float) $row['percentage']];
            $this->assertEquals($expected, $data['budget']['rows'][$index]);
        }
        $this->assertSame('65.00', collect($legacy['budgets'])->where('kind', 'income')->firstWhere('category', 'membership')['percentage']);
        $this->assertNull(collect($data['budget']['rows'])->where('kind', 'income')->firstWhere('category', 'merchandise')['percentage']);
        $this->assertSame($legacy, (new BudgetActual)->forMonth($this->gym, '2026-12'));
        $this->assertSame('200.00', $data['financial']['totals']['income']);
        $this->assertSame('200.00', $data['financial']['totals']['expenses']);
    }

    public function test_empty_modules_do_not_hide_other_measures_and_cash_forecast_is_not_relabelled_accrual(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('sustainability').'?from=2000-01-01&to=2000-01-01')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('membership.retention_rate', null)
            ->assertJsonPath('utilization.classes', [])->assertJsonPath('budget.totals.0.percentage', null)
            ->assertJsonPath('cash_flow_forecast.method', 'no_history')->assertJsonPath('cash_flow_forecast.buckets', []);
        $this->income('other', '100.00', '2026-10-01');
        $this->expense('400.00', '2026-10-01', 'goods_receipt');
        $data = $this->actingAs($this->owner)->getJson($this->decisionUrl('sustainability').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('meta.has_data', true)->assertJsonPath('cash_flow_forecast.basis', 'cash_flow')->json();
        $this->assertSame('0.00', $data['financial']['totals']['income']);
        $this->assertSame('0.00', $data['cash_flow_forecast']['history'][0]['expenses']);
        $this->assertSame('400.00', (new Forecast)->forPeriod($this->gym, new Period('2026-11-02', '2026-11-02'))['history'][0]['expenses']);
    }
}
