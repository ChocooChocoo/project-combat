<?php

namespace Tests\Feature;

use App\Models\StaffAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-122: clock-in checks the distance to the gym's map pin when a radius is set. */
class AttendanceLocationTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttendanceGym();
    }

    private function pin(?int $radius): void
    {
        $this->gym->forceFill(['latitude' => 14.5995, 'longitude' => 120.9842, 'attendance_radius_m' => $radius])->save();
    }

    private function clockIn(array $body = [])
    {
        return $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'), $body);
    }

    public function test_no_radius_allows_clock_in_without_location(): void
    {
        $this->pin(null);
        $this->clockIn()->assertCreated();
    }

    public function test_radius_with_pin_needs_a_location(): void
    {
        $this->pin(100);
        $this->clockIn()->assertStatus(422)->assertJsonValidationErrors('location');
        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_inside_the_radius_saves_distance_and_coordinates(): void
    {
        $this->pin(100);
        // 0.00027 degrees of latitude is about 30 m.
        $this->clockIn(['latitude' => 14.59977, 'longitude' => 120.9842])
            ->assertCreated()
            ->assertJsonPath('today.clock_in_distance_m', fn ($d) => $d >= 20 && $d <= 40);

        $row = StaffAttendance::first();
        $this->assertEqualsWithDelta(14.59977, (float) $row->clock_in_latitude, 0.0000001);
        $this->assertEqualsWithDelta(120.9842, (float) $row->clock_in_longitude, 0.0000001);
    }

    public function test_outside_the_radius_is_refused(): void
    {
        $this->pin(100);
        // About 500 m north.
        $this->clockIn(['latitude' => 14.6040, 'longitude' => 120.9842])
            ->assertStatus(422)->assertJsonValidationErrors('location');
        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_radius_without_a_pin_is_skipped(): void
    {
        $this->gym->forceFill(['latitude' => null, 'longitude' => null, 'attendance_radius_m' => 100])->save();
        $this->clockIn()->assertCreated();
    }

    public function test_setup_accepts_and_rejects_radius_values(): void
    {
        $url = $this->url('setup');
        $this->actingAs($this->owner)->putJson($url, ['attendance_radius_m' => 100])->assertOk();
        $this->assertSame(100, $this->gym->fresh()->attendance_radius_m);
        $this->actingAs($this->owner)->putJson($url, ['attendance_radius_m' => null])->assertOk();
        $this->assertNull($this->gym->fresh()->attendance_radius_m);
        $this->actingAs($this->owner)->putJson($url, ['attendance_radius_m' => 10])->assertStatus(422);
        $this->actingAs($this->owner)->putJson($url, ['attendance_radius_m' => 6000])->assertStatus(422);
    }

    public function test_my_attendance_reports_whether_the_check_is_on(): void
    {
        $this->pin(100);
        $this->actingAs($this->employee)->getJson($this->url('my-attendance'))
            ->assertJsonPath('settings.location_check', true)
            ->assertJsonPath('settings.radius_m', 100);

        $this->pin(null);
        $this->actingAs($this->employee)->getJson($this->url('my-attendance'))
            ->assertJsonPath('settings.location_check', false);
    }
}
