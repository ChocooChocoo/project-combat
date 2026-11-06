<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\OvertimeRequest;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-09 on the new HR routes: another gym's ids do not exist. */
class StaffAttendanceTenancyTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    public function test_another_gym_cannot_touch_attendance_overtime_or_performance_notes(): void
    {
        $this->setUpAttendanceGym();
        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));

        $entry = StaffAttendance::create([
            'user_id' => $this->employee->id,
            'work_date' => '2026-11-05',
            'clock_in_at' => '2026-11-05 09:00:00',
            'status' => 'present',
            'source' => 'self',
        ]);

        $overtime = OvertimeRequest::create([
            'user_id' => $this->employee->id,
            'work_date' => '2026-11-05',
            'kind' => 'manual',
            'requested_minutes' => 30,
            'reason' => 'Stayed late.',
            'status' => 'pending',
        ]);

        $other = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $other->addPerson($otherOwner, GymPersonType::Owner);
        app(CurrentGym::class)->set($other);

        $base = "/api/gyms/{$other->id}";

        $this->actingAs($otherOwner)->putJson("{$base}/staff-attendance/{$entry->id}", [
            'clock_in_time' => '08:00',
            'reason' => 'Test.',
        ])->assertNotFound();

        $this->actingAs($otherOwner)->getJson("{$base}/staff-attendance/{$entry->id}/changes")->assertNotFound();

        $this->actingAs($otherOwner)->postJson("{$base}/overtime-requests/{$overtime->id}/decision", [
            'decision' => 'approve',
        ])->assertNotFound();

        $this->actingAs($otherOwner)->getJson("{$base}/staff/{$this->employee->id}/performance-notes")->assertNotFound();

        $this->actingAs($otherOwner)->getJson("{$base}/staff-attendance")
            ->assertOk()
            ->assertJsonCount(0, 'attendance');
    }
}
