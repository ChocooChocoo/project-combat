<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PayrollSetup;
use Tests\TestCase;

/** R-99: staff grace is at least 10 minutes. R-100: pay lines count present, late and absent days. */
class StaffGraceAndLateDaysTest extends TestCase
{
    use PayrollSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayrollGym();
    }

    public function test_staff_grace_below_ten_minutes_is_refused_everywhere(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('payroll/settings'), ['staff_grace_minutes' => 9])
            ->assertUnprocessable()->assertJsonValidationErrors('staff_grace_minutes');
        $this->actingAs($this->owner)->putJson($this->url('setup'), ['staff_grace_minutes' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('staff_grace_minutes');

        $this->actingAs($this->owner)->putJson($this->url('payroll/settings'), ['staff_grace_minutes' => 10])->assertOk();
        $this->assertSame(10, $this->gym->fresh()->staff_grace_minutes);
    }

    public function test_pay_line_and_payslip_show_present_late_and_absent_days(): void
    {
        $this->payrollProfile($this->employee);
        $this->payrollSchedule($this->employee);
        // 1 to 15 November 2026 has 10 weekdays. Mark them all present, then make two late and remove one.
        $this->markPresent($this->employee);
        $rows = $this->gym->staffAttendance()->where('user_id', $this->employee->id)->orderBy('work_date')->get();
        $rows[0]->forceFill(['status' => 'late', 'late_minutes' => 15])->save();
        $rows[1]->forceFill(['status' => 'late', 'late_minutes' => 5])->save();
        $rows[2]->forceFill(['status' => 'absent', 'clock_in_at' => null, 'clock_out_at' => null, 'worked_minutes' => 0])->save();

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $line = collect($this->actingAs($this->hr)->getJson($this->url("payroll/runs/{$run->id}"))->assertOk()->json('lines'))
            ->firstWhere('user_id', $this->employee->id);

        $this->assertSame(7, $line['days_present']);
        $this->assertSame(2, $line['days_late']);
        $this->assertSame(1, $line['days_absent']);
        $this->assertSame(9, $line['days_worked']);
        $this->assertSame(20, $line['late_minutes']);
    }
}
