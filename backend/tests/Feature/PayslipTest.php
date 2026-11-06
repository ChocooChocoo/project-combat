<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\PayrollLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PayrollSetup;
use Tests\TestCase;

/** R-62: own released payslips only, and staff history behind the payroll permission. */
class PayslipTest extends TestCase
{
    use PayrollSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayrollGym();
        $this->payrollProfile($this->employee);
        $this->markPresent($this->employee);
    }

    public function test_own_and_released_only_and_another_employee_gets_404(): void
    {
        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->actingAs($this->employee)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(0, 'payslips');

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('run.status', 'released');

        $index = $this->actingAs($this->employee)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(1, 'payslips');
        $index->assertJsonPath('payslips.0.run_id', $run->id)
            ->assertJsonPath('payslips.0.base_pay', '15000.00')
            ->assertJsonPath('payslips.0.attendance_deduction', '0.00')
            ->assertJsonPath('payslips.0.gross_pay', '15000.00')
            ->assertJsonPath('payslips.0.net_pay', '14275.00');

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->where('user_id', $this->employee->id)->firstOrFail();

        $show = $this->actingAs($this->employee)->getJson("/api/me/payslips/{$line->id}")->assertOk();
        $show->assertJsonPath('payslip.id', $line->id)
            ->assertJsonPath('line.user_id', $this->employee->id)
            ->assertJsonPath('run.id', $run->id)
            ->assertJsonPath('payslip.base_pay', '15000.00')
            ->assertJsonPath('payslip.attendance_deduction', '0.00')
            ->assertJsonPath('payslip.holiday_premium', '0.00')
            ->assertJsonPath('payslip.overtime_pay', '0.00')
            ->assertJsonPath('payslip.session_pay', '0.00')
            ->assertJsonPath('payslip.gross_pay', '15000.00')
            ->assertJsonPath('payslip.sss_employee', '50.00')
            ->assertJsonPath('payslip.philhealth_employee', '375.00')
            ->assertJsonPath('payslip.pagibig_employee', '300.00')
            ->assertJsonPath('payslip.tax', '0.00')
            ->assertJsonPath('payslip.net_pay', '14275.00')
            ->assertJsonPath('payslip.scheduled_days', 10)
            ->assertJsonPath('payslip.days_worked', 10)
            ->assertJsonPath('payslip.days_absent', 0)
            ->assertJsonPath('payslip.paid_leave_days', 0)
            ->assertJsonPath('payslip.unpaid_leave_days', 0)
            ->assertJsonPath('payslip.late_minutes', 0)
            ->assertJsonPath('payslip.undertime_minutes', 0)
            ->assertJsonPath('payslip.overtime_minutes', 0)
            ->assertJsonPath('payslip.sessions_count', 0)
            ->assertJsonPath('payslip.warnings', []);
        $this->assertNotEmpty($show->json('items'));

        $other = User::factory()->create(['first_name' => 'Other', 'last_name' => 'Employee']);
        $this->gym->addPerson($other, GymPersonType::Employee);

        $this->actingAs($other)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(0, 'payslips');
        $this->actingAs($other)->getJson("/api/me/payslips/{$line->id}")->assertNotFound();
    }

    public function test_released_payslip_exposes_the_stored_line_breakdown(): void
    {
        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->where('user_id', $this->employee->id)->firstOrFail();
        $line->update([
            'base_pay' => '14000.00',
            'attendance_deduction' => '15028.72',
            'holiday_premium' => '0.00',
            'overtime_pay' => '0.00',
            'session_pay' => '7200.00',
            'gross_pay' => '6171.28',
            'sss_employee' => '0.00',
            'philhealth_employee' => '0.00',
            'pagibig_employee' => '0.00',
            'tax' => '0.00',
            'net_pay' => '6171.28',
            'scheduled_days' => 10,
            'days_worked' => 0,
            'days_absent' => 10,
        ]);

        $this->actingAs($this->employee)->getJson('/api/me/payslips')
            ->assertOk()
            ->assertJsonPath('payslips.0.base_pay', '14000.00')
            ->assertJsonPath('payslips.0.attendance_deduction', '15028.72')
            ->assertJsonPath('payslips.0.session_pay', '7200.00')
            ->assertJsonPath('payslips.0.gross_pay', '6171.28')
            ->assertJsonPath('payslips.0.net_pay', '6171.28');

        $show = $this->actingAs($this->employee)->getJson("/api/me/payslips/{$line->id}")->assertOk();
        $show->assertJsonPath('payslip.base_pay', '14000.00')
            ->assertJsonPath('payslip.attendance_deduction', '15028.72')
            ->assertJsonPath('payslip.session_pay', '7200.00')
            ->assertJsonPath('payslip.gross_pay', '6171.28')
            ->assertJsonPath('payslip.sss_employee', '0.00')
            ->assertJsonPath('payslip.philhealth_employee', '0.00')
            ->assertJsonPath('payslip.pagibig_employee', '0.00')
            ->assertJsonPath('payslip.tax', '0.00')
            ->assertJsonPath('payslip.net_pay', '6171.28')
            ->assertJsonPath('payslip.scheduled_days', 10)
            ->assertJsonPath('payslip.days_worked', 0)
            ->assertJsonPath('payslip.days_absent', 10);

        $this->actingAs($this->hr)->getJson($this->url("payroll/runs/{$run->id}"))
            ->assertOk()
            ->assertJsonPath('payslips.0.base_pay', '14000.00')
            ->assertJsonPath('payslips.0.attendance_deduction', '15028.72')
            ->assertJsonPath('payslips.0.session_pay', '7200.00')
            ->assertJsonPath('payslips.0.gross_pay', '6171.28')
            ->assertJsonPath('payslips.0.net_pay', '6171.28');
    }

    public function test_a_draft_payslip_is_not_visible(): void
    {
        $draft = $this->payrollRun('2026-11-16', '2026-11-30');
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$draft->id}/compute"))->assertOk();

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $draft->id)->firstOrFail();

        $this->actingAs($this->employee)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(0, 'payslips');
        $this->actingAs($this->employee)->getJson("/api/me/payslips/{$line->id}")->assertNotFound();
    }

    public function test_a_removed_employee_still_sees_released_payslips(): void
    {
        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->where('user_id', $this->employee->id)->firstOrFail();

        $this->gym->employees()->where('user_id', $this->employee->id)->delete();
        $this->gym->people()->detach($this->employee->id);

        $this->actingAs($this->employee)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(1, 'payslips');
        $this->actingAs($this->employee)->getJson("/api/me/payslips/{$line->id}")->assertOk();
    }

    public function test_staff_history_needs_the_payroll_view_permission(): void
    {
        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $viewer = User::factory()->create(['first_name' => 'Vera', 'last_name' => 'Cruz']);
        $this->gym->addPerson($viewer, GymPersonType::Employee);
        $this->givePayrollRole($viewer, ['payroll.runs.view'], 'Payroll viewer');

        $this->actingAs($viewer)->getJson($this->url("payroll/runs/{$run->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'payslips')
            ->assertJsonPath('payslips.0.user_id', $this->employee->id)
            ->assertJsonPath('payslips.0.base_pay', '15000.00')
            ->assertJsonPath('payslips.0.attendance_deduction', '0.00')
            ->assertJsonPath('payslips.0.gross_pay', '15000.00')
            ->assertJsonPath('payslips.0.net_pay', '14275.00');

        $plain = User::factory()->create(['first_name' => 'Pia', 'last_name' => 'Santos']);
        $this->gym->addPerson($plain, GymPersonType::Employee);

        $this->actingAs($plain)->getJson($this->url("payroll/runs/{$run->id}"))->assertForbidden();
    }
}
