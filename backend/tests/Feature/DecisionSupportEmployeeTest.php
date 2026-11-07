<?php

namespace Tests\Feature;

use App\Models\StaffAttendance;
use App\Support\Finance\Reports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportEmployeeTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_stored_attendance_minutes_and_operational_workload_are_not_recomputed(): void
    {
        foreach (['present', 'present', 'late', 'absent'] as $index => $status) {
            StaffAttendance::create(['user_id' => $this->owner->id, 'work_date' => '2026-11-0'.($index + 1),
                'status' => $status, 'worked_minutes' => $status === 'absent' ? 0 : ($status === 'late' ? 450 : 480),
                'late_minutes' => $status === 'late' ? 15 : 0, 'undertime_minutes' => $status === 'late' ? 30 : 0,
                'missing_clock_out' => $index === 0, 'explanation' => 'PRIVATE STAFF NOTE']);
        }
        $session = $this->occurrence('2026-11-02 10:00:00', ['flagged_at' => now()]);
        $this->markedBooking($session, 'present');
        $this->occurrence('2026-11-03 10:00:00');
        $this->occurrence('2026-11-08 10:00:00');
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('employees').'?from=2026-11-01&to=2026-11-07')->assertOk()
            ->assertJsonPath('summary.present', 2)->assertJsonPath('summary.late', 1)->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.attendance_rate', 75)->assertJsonPath('summary.late_rate', 33.33)
            ->assertJsonPath('summary.worked_minutes', 1410)->assertJsonPath('summary.late_minutes', 15)
            ->assertJsonPath('summary.undertime_minutes', 30)->assertJsonPath('summary.missing_clock_out', 1)
            ->assertJsonPath('workload.0.sessions', 2)->assertJsonPath('workload.0.delivered_sessions', 1)
            ->assertJsonPath('workload.0.scheduled_minutes', 120)->assertJsonPath('workload.0.delivered_minutes', 60);
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
        $this->assertSame(4, StaffAttendance::where('gym_id', $this->gym->id)->count());
        $this->getJson($this->decisionUrl('employees').'?from=2026-11-03&to=2026-11-03')->assertOk()
            ->assertJsonPath('summary.late', 1)->assertJsonPath('summary.present', 0)->assertJsonPath('summary.attendance_rate', 100);
    }

    public function test_released_lines_and_exact_payroll_permission_control_individual_pay(): void
    {
        $this->payrollFixture('2026-11-05', lines: [
            ['gross_pay' => '10000.00', 'net_pay' => '9000.00', 'sss_employer' => '500.00', 'ec_employer' => '10.00', 'philhealth_employer' => '250.00', 'pagibig_employer' => '100.00'],
            ['gross_pay' => '5000.00', 'net_pay' => '4500.00', 'sss_employer' => '200.00', 'ec_employer' => '5.00', 'philhealth_employer' => '150.00', 'pagibig_employer' => '75.00'],
        ]);
        foreach ([['2026-11-06', 'draft'], ['2026-11-07', 'in_review'], ['2026-10-31', 'released']] as [$end, $status]) {
            $this->payrollFixture($end, $status, [['gross_pay' => '99999.00', 'net_pay' => '99999.00']]);
        }
        foreach ([[], ['hr.staff.manage'], ['finance.payments.view'], ['payroll.runs.view']] as $extra) {
            $user = $this->employeeWithFinanceRole(['decision_support.general.view', ...$extra]);
            $response = $this->actingAs($user)->getJson($this->decisionUrl('employees').'?from=2026-11-01&to=2026-11-07')->assertOk()
                ->assertJsonPath('payroll.totals.gross', '15000.00')->assertJsonPath('payroll.totals.net', '13500.00')
                ->assertJsonPath('payroll.totals.employer_statutory', '1290.00')->assertJsonPath('payroll.totals.count', 2)
                ->assertJsonPath('pay_visible', $extra === ['payroll.runs.view']);
            $this->assertSame(app(Reports::class)->payrollSummary($this->gym, '2026-11-01', '2026-11-07'), $response->json('payroll'));
            if ($extra === ['payroll.runs.view']) {
                $response->assertJsonCount(2, 'payroll_by_employee');
                $this->assertSame(15000.0, (float) collect($response->json('payroll_by_employee'))->sum('gross'));
                $this->assertSame(13500.0, (float) collect($response->json('payroll_by_employee'))->sum('net'));
                $this->assertSame(1290.0, (float) collect($response->json('payroll_by_employee'))->sum('employer_statutory'));
            } else $response->assertJsonMissingPath('payroll_by_employee');
            $this->assertStringNotContainsString('monthly_rate', $response->getContent());
            $this->assertStringNotContainsString('PRIVATE WARNING', $response->getContent());
            foreach (['attendance', 'workload'] as $key) {
                $this->assertStringNotContainsString('gross', json_encode($response->json($key)));
                $this->assertStringNotContainsString('net_pay', json_encode($response->json($key)));
            }
        }
        $this->actingAs($this->owner)->getJson($this->decisionUrl('employees').'?from=2026-11-01&to=2026-11-07')->assertOk()->assertJsonPath('pay_visible', true);
        $payrollOnly = $this->employeeWithFinanceRole(['payroll.runs.view']);
        $this->actingAs($payrollOnly)->getJson($this->decisionUrl('employees').'?from=2026-11-01&to=2026-11-07')->assertForbidden();
    }

    public function test_empty_day_has_null_rates_no_backfill_and_decimal_payroll(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('employees').'?from=2026-11-07&to=2026-11-07')->assertOk()
            ->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.attendance_rate', null)
            ->assertJsonPath('summary.worked_minutes', 0)->assertJsonPath('payroll.totals.net', '0.00')
            ->assertJsonPath('payroll_by_employee', []);
        $this->assertDatabaseCount('staff_attendance', 0);
    }
}
