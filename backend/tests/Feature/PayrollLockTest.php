<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PayrollSetup;
use Tests\TestCase;

/** R-61: a date inside an in-review or released run is closed; rejection reopens it. */
class PayrollLockTest extends TestCase
{
    use PayrollSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayrollGym();
        $this->payrollProfile($this->employee);
        $this->payrollSchedule($this->employee);
        $this->markPresent($this->employee);
    }

    public function test_in_review_and_released_runs_lock_attendance_overtime_leave_and_holidays(): void
    {
        $leave = $this->actingAs($this->employee)->postJson($this->url('leave-requests'), [
            'type' => 'vacation',
            'starts_on' => '2026-11-05',
            'ends_on' => '2026-11-06',
            'reason' => 'Family time.',
        ])->assertCreated()->json('request.id');

        $overtime = $this->actingAs($this->employee)->postJson($this->url('my-overtime-requests'), [
            'work_date' => '2026-11-05',
            'minutes' => 45,
            'reason' => 'Stayed to close the gym.',
        ])->assertCreated()->json('request.id');

        $holiday = $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-11-05',
            'name' => 'Gym anniversary',
        ])->assertCreated()->json('holiday.id');

        $attendance = $this->payrollAttendance($this->employee, '2026-11-05');

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->assertLocked($attendance->id, $overtime, $leave);

        $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-11-06',
            'name' => 'Another day',
        ])->assertStatus(409);

        $this->actingAs($this->owner)->deleteJson($this->url("holidays/{$holiday}"))->assertStatus(409);

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'reject',
            'reason' => 'Recheck the overtime.',
        ])->assertOk()->assertJsonPath('run.status', 'draft');

        $this->actingAs($this->owner)->putJson($this->url("staff-attendance/{$attendance->id}"), [
            'clock_in_time' => '09:05',
            'clock_out_time' => '17:00',
            'reason' => 'Corrected after the return.',
        ])->assertOk();

        $this->actingAs($this->owner)->postJson($this->url("overtime-requests/{$overtime}/decision"), [
            'decision' => 'approve',
            'minutes' => 45,
        ])->assertOk();

        $this->actingAs($this->owner)->postJson($this->url("leave-requests/{$leave}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-11-06',
            'name' => 'Reopened day',
        ])->assertCreated();

        $this->actingAs($this->owner)->deleteJson($this->url("holidays/{$holiday}"))->assertOk();

        $run->refresh();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('run.status', 'released');

        $this->actingAs($this->owner)->putJson($this->url("staff-attendance/{$attendance->id}"), [
            'clock_in_time' => '09:00',
            'reason' => 'Too late to change.',
        ])->assertStatus(409);

        $this->actingAs($this->owner)->postJson($this->url('holidays'), [
            'date' => '2026-11-09',
            'name' => 'Blocked after release',
        ])->assertStatus(409);
    }

    private function assertLocked(int $attendance, int $overtime, int $leave): void
    {
        $this->actingAs($this->owner)->putJson($this->url("staff-attendance/{$attendance}"), [
            'clock_in_time' => '09:00',
            'reason' => 'Blocked correction.',
        ])->assertStatus(409);

        $this->actingAs($this->owner)->postJson($this->url("overtime-requests/{$overtime}/decision"), [
            'decision' => 'approve',
        ])->assertStatus(409);

        $this->actingAs($this->owner)->postJson($this->url("leave-requests/{$leave}/decision"), [
            'decision' => 'approve',
        ])->assertStatus(409);
    }
}
