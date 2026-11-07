<?php

namespace Tests\Feature;

use App\Models\GymVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportAttendanceTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_known_markers_reconcile_after_booking_and_session_cancellation(): void
    {
        $session = $this->occurrence();
        foreach (['present', 'present', 'late', 'absent', 'no_show'] as $index => $marker) {
            $this->markedBooking($session, $marker, $index === 0 ? 'cancelled' : 'booked');
        }
        $this->markedBooking($session, null, 'requested');
        $this->markedBooking($session, null);
        $this->markedBooking($session, 'unexpected');
        $session->update(['status' => 'cancelled']);
        GymVisit::create(['user_id' => $this->member->id, 'checked_in_at' => '2026-11-02 10:00:00', 'checked_in_by' => $this->owner->id, 'method' => 'manual']);

        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('attendance').'?from=2026-11-02&to=2026-11-02')->assertOk()
            ->assertJsonPath('summary.present', 2)->assertJsonPath('summary.late', 1)
            ->assertJsonPath('summary.absent', 1)->assertJsonPath('summary.no_show', 1)
            ->assertJsonPath('summary.attended', 3)->assertJsonPath('summary.marked', 5)
            ->assertJsonPath('summary.attendance_rate', 60)->assertJsonPath('summary.no_show_rate', 20)
            ->assertJsonPath('summary.awaiting_attendance', 1)->assertJsonPath('summary.unknown_markers', 1)
            ->assertJsonPath('summary.cancelled_session_markers', 5)
            ->assertJsonPath('weekdays.1.class_attended', 3)->assertJsonPath('weekdays.1.gym_visits', 1)
            ->assertJsonPath('weekdays.1.total', 4)->assertJsonPath('hours.10.total', 4)
            ->assertJsonPath('peaks.weekdays', [1])->assertJsonPath('peaks.hours', [10]);
        $this->assertCount(7, $response->json('weekdays'));
        $this->assertCount(24, $response->json('hours'));
        $this->assertStringContainsString('cancelled', implode(' ', $response->json('meta.notes')));
        $this->assertSame(5, DB::table('session_bookings')->where('gym_id', $this->gym->id)->whereIn('attendance', ['present', 'late', 'absent', 'no_show'])->count());
        $ledger = app(\App\Support\AttendanceLedger::class)->rows(['from' => '2026-11-02', 'to' => '2026-11-02']);
        $this->assertSame($response->json('summary.marked'), $ledger->whereIn('attendance', ['present', 'late', 'absent', 'no_show'])->count());
        $this->assertSame($response->json('summary.attended'), $ledger->whereIn('attendance', ['present', 'late'])->count());
    }

    public function test_final_day_microseconds_and_tied_peaks_use_session_start_not_arrival(): void
    {
        foreach (['2026-11-01 00:00:00', '2026-11-02 23:59:59.999999', '2026-11-03 00:00:00'] as $start) {
            $session = $this->occurrence();
            DB::table('training_sessions')->where('id', $session->id)->update(['starts_at' => $start]);
            $booking = $this->markedBooking($session, 'present');
            DB::table('session_bookings')->where('id', $booking->id)->update(['check_in_at' => '2026-10-31 20:00:00']);
        }
        $this->actingAs($this->owner)->getJson($this->decisionUrl('attendance').'?from=2026-11-01&to=2026-11-02')
            ->assertOk()->assertJsonPath('summary.attended', 2)->assertJsonPath('peaks.weekdays', [0, 1])->assertJsonPath('peaks.hours', [0, 23]);
    }

    public function test_empty_and_unmarked_classes_have_null_rates_and_no_peaks(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('attendance').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.attendance_rate', null)->assertJsonPath('peaks.hours', []);
        $this->occurrence();
        $this->actingAs($this->owner)->getJson($this->decisionUrl('attendance').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('meta.has_data', true)->assertJsonPath('classes.0.attendance_rate', null)->assertJsonPath('summary.marked', 0);
    }
}
