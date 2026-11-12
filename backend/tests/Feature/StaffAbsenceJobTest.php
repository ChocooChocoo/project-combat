<?php

namespace Tests\Feature;

use App\Models\GymHoliday;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-56: the nightly job marks real absences and skips holidays, leave and rest days. */
class StaffAbsenceJobTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttendanceGym();
        $this->profileFor($this->employee, ['started_on' => '2026-01-01']);
    }

    private function runFor(string $date): void
    {
        $this->artisan('app:mark-staff-absences', ['--date' => $date])->assertExitCode(0);
    }

    public function test_a_working_day_without_a_row_becomes_absent(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        $this->runFor('2026-11-10');

        $row = StaffAttendance::firstOrFail();
        $this->assertSame('2026-11-10', $row->work_date->toDateString());
        $this->assertSame($this->employee->id, $row->user_id);
        $this->assertSame('absent', $row->status->value);
        $this->assertSame('system', $row->source->value);
    }

    /** R-116: the default daily run tells the employee and the owner; backfills stay quiet. */
    public function test_only_the_daily_run_sends_absence_notices(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00'));
        $this->runFor('2026-11-09');
        $this->assertSame(0, $this->employee->notifications()->count());

        $this->artisan('app:mark-staff-absences')->assertExitCode(0);
        $this->assertSame(1, $this->employee->notifications()->count());
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertStringContainsString('absence', $this->owner->notifications()->first()->data['title']);
    }

    public function test_the_job_is_idempotent(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        $this->runFor('2026-11-10');
        $this->runFor('2026-11-10');

        $this->assertSame(1, StaffAttendance::count());
    }

    public function test_a_gym_holiday_is_not_an_absence(): void
    {
        GymHoliday::create(['date' => '2026-11-10', 'name' => 'Founding day']);
        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        $this->runFor('2026-11-10');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_a_national_holiday_is_not_an_absence(): void
    {
        $this->travelTo(Carbon::parse('2026-12-25 08:00:00'));
        $this->runFor('2026-12-25');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_approved_leave_is_not_an_absence(): void
    {
        LeaveRequest::create([
            'user_id' => $this->employee->id,
            'type' => 'vacation',
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-10',
            'working_days' => 1,
            'reason' => 'Family trip.',
            'status' => 'approved',
        ]);

        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        $this->runFor('2026-11-10');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_a_saturday_holiday_is_not_an_absence_when_saturday_is_worked(): void
    {
        $this->gym->update(['working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']]);
        GymHoliday::create(['date' => '2026-11-14', 'name' => 'Gym event']);

        $this->travelTo(Carbon::parse('2026-11-14 08:00:00'));
        $this->runFor('2026-11-14');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_a_saturday_without_a_holiday_is_an_absence_when_saturday_is_worked(): void
    {
        $this->gym->update(['working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']]);

        $this->travelTo(Carbon::parse('2026-11-14 08:00:00'));
        $this->runFor('2026-11-14');

        $row = StaffAttendance::firstOrFail();
        $this->assertSame('2026-11-14', $row->work_date->toDateString());
        $this->assertSame('absent', $row->status->value);
    }

    public function test_dates_before_the_start_or_after_the_end_are_skipped(): void
    {
        $this->gym->employees()->where('user_id', $this->employee->id)->firstOrFail()
            ->update(['started_on' => '2026-11-10', 'ended_on' => '2026-11-10']);

        $this->travelTo(Carbon::parse('2026-11-12 08:00:00'));
        $this->runFor('2026-11-09');
        $this->runFor('2026-11-11');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_a_missing_clock_out_is_flagged(): void
    {
        StaffAttendance::create([
            'user_id' => $this->employee->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 09:00:00',
            'status' => 'present',
            'source' => 'self',
        ]);

        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        $this->runFor('2026-11-09');

        $this->assertTrue(StaffAttendance::firstOrFail()->missing_clock_out);
    }
}
