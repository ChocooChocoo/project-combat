<?php

namespace Tests\Feature;

use App\Models\OvertimeRequest;
use App\Models\StaffAttendance;
use App\Notifications\OvertimeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-56: clock in, clock out, lateness, corrections and the missing clock-out flag. */
class StaffAttendanceTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttendanceGym();
        $this->scheduleFor($this->employee);
    }

    public function test_clock_in_and_out_compute_late_and_undertime_minutes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:11:00'));

        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))
            ->assertCreated()
            ->assertJsonPath('today.status', 'late')
            ->assertJsonPath('today.late_minutes', 11)
            ->assertJsonPath('today.scheduled_start', '09:00');

        $this->travelTo(Carbon::parse('2026-11-05 16:30:00'));

        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-out'))
            ->assertOk()
            ->assertJsonPath('today.undertime_minutes', 30)
            ->assertJsonPath('today.worked_minutes', 439);
    }

    public function test_clock_in_inside_the_grace_is_not_late(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:10:00'));

        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))
            ->assertCreated()
            ->assertJsonPath('today.status', 'present')
            ->assertJsonPath('today.late_minutes', 0);
    }

    public function test_a_second_clock_in_on_the_same_day_is_refused(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 08:55:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertStatus(409);
    }

    public function test_a_correction_records_the_before_after_who_and_why(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:11:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-05 12:00:00'));
        $id = StaffAttendance::firstOrFail()->id;

        $this->actingAs($this->owner)->putJson($this->url("staff-attendance/{$id}"), [
            'clock_in_time' => '09:00',
            'clock_out_time' => '17:00',
            'reason' => 'The employee clocked in from the wrong device.',
        ])->assertOk()
            ->assertJsonPath('attendance.status', 'present')
            ->assertJsonPath('attendance.late_minutes', 0)
            ->assertJsonPath('attendance.worked_minutes', 480);

        $this->actingAs($this->owner)->getJson($this->url("staff-attendance/{$id}/changes"))
            ->assertOk()
            ->assertJsonCount(1, 'changes')
            ->assertJsonPath('changes.0.changed_by_name', 'Owen Reyes')
            ->assertJsonPath('changes.0.reason', 'The employee clocked in from the wrong device.')
            ->assertJsonPath('changes.0.before.clock_in_time', '09:11')
            ->assertJsonPath('changes.0.after.clock_out_time', '17:00');
    }

    public function test_the_desk_list_needs_the_attendance_permission(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('staff-attendance'))->assertForbidden();
        $this->actingAs($this->employee)->getJson($this->url('my-attendance'))->assertOk();
    }

    public function test_missing_clock_out_is_flagged_by_the_daily_job(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-06 08:00:00'));
        $this->artisan('app:mark-staff-absences', ['--date' => '2026-11-05'])->assertExitCode(0);

        $this->assertTrue(StaffAttendance::firstOrFail()->missing_clock_out);
    }

    public function test_a_present_day_cannot_be_explained_but_a_late_one_can(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 08:30:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();
        $id = StaffAttendance::firstOrFail()->id;

        $this->actingAs($this->employee)->putJson($this->url("my-attendance/{$id}/explanation"), [
            'explanation' => 'Early start.',
        ])->assertStatus(409);

        StaffAttendance::firstOrFail()->update(['status' => 'late']);
        $this->actingAs($this->employee)->putJson($this->url("my-attendance/{$id}/explanation"), [
            'explanation' => 'The bus was late.',
        ])->assertOk()->assertJsonPath('attendance.explanation', 'The bus was late.');
    }

    public function test_a_removed_employee_keeps_their_attendance_history(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();
        $id = StaffAttendance::firstOrFail()->id;

        $this->actingAs($this->owner)->deleteJson($this->url("staff/{$this->employee->id}"))->assertOk();

        $this->assertDatabaseHas('staff_attendance', [
            'id' => $id,
            'gym_id' => $this->gym->id,
            'user_id' => $this->employee->id,
        ]);

        $this->actingAs($this->owner)
            ->getJson($this->url('staff-attendance?from=2026-11-05&to=2026-11-05'))
            ->assertOk()
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('attendance.0.id', $id)
            ->assertJsonPath('attendance.0.user_name', 'Ella Santos');
    }

    public function test_clock_out_creates_an_automatic_overtime_request(): void
    {
        Notification::fake();

        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();
        $this->travelTo(Carbon::parse('2026-11-05 18:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-out'))->assertOk();

        $request = OvertimeRequest::firstOrFail();
        $this->assertSame(60, $request->requested_minutes);
        $this->assertSame('after_shift', $request->kind->value);
        $this->assertSame('pending', $request->status->value);
        Notification::assertSentTo($this->owner, OvertimeRequested::class);
    }
}
