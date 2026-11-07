<?php

namespace Tests\Feature;

use App\Models\SessionBooking;
use App\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class SessionReminderTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(9, 0));
        $this->applicant->notifications()->delete();
    }

    private function runReminders(int $expected): void
    {
        $this->app->forgetScopedInstances();
        $this->artisan('app:send-session-reminders')->expectsOutput("{$expected} session reminders sent.")->assertSuccessful();
    }

    public function test_a_booked_athlete_is_reminded_inside_the_lead_window_once(): void
    {
        $this->runReminders(1);
        $this->runReminders(0);
        $notice = $this->applicant->notifications()->sole();
        $this->assertSame($this->bookingId, $notice->data['booking_id']);
        $this->assertSame($this->sessionId, $notice->data['session_id']);
        $this->assertSame("/gyms/{$this->gym->id}/sessions/{$this->sessionId}", $notice->data['url']);
        $this->assertStringContainsString('Boxing', $notice->data['message']);
        $this->assertStringContainsString($this->room->name, $notice->data['message']);
        $this->assertNotNull(SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->reminder_sent_at);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_a_booking_outside_the_window_is_not_reminded(): void
    {
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 59));
        $this->runReminders(0);
        $this->travelTo(now()->setTime(10, 0));
        $this->runReminders(0);
        $this->travelTo(now()->setTime(11, 0));
        $this->runReminders(0);
        $this->assertSame(0, $this->applicant->notifications()->count());
    }

    public function test_requested_waitlisted_and_cancelled_bookings_are_not_reminded(): void
    {
        foreach (['requested', 'waitlisted', 'cancelled'] as $status) {
            SessionBooking::withoutGlobalScope('gym')->whereKey($this->bookingId)->update(['status' => $status]);
            $this->runReminders(0);
        }
        $this->assertSame(0, $this->applicant->notifications()->count());
    }

    public function test_a_cancelled_session_is_not_reminded(): void
    {
        TrainingSession::withoutGlobalScope('gym')->whereKey($this->sessionId)->update(['status' => 'cancelled']);
        $this->runReminders(0);
    }

    public function test_rescheduling_a_session_clears_the_reminder_marker(): void
    {
        $this->runReminders(1);
        $this->actingAs($this->owner)->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['name' => 'Renamed']))->assertOk();
        $this->assertNotNull(SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->reminder_sent_at);
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData([
            'starts_at' => '2026-11-09 10:30:00', 'ends_at' => '2026-11-09 11:30:00',
        ]))->assertOk();
        $this->assertNull(SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->reminder_sent_at);
        $this->travelTo(now()->setTime(9, 30));
        $this->runReminders(1);
    }

    public function test_the_gym_lead_time_is_respected(): void
    {
        $this->gym->update(['session_reminder_minutes' => 30]);
        $this->runReminders(0);
        $this->gym->update(['session_reminder_minutes' => 120]);
        $this->runReminders(1);
    }

    public function test_unapproved_gyms_are_skipped(): void
    {
        $this->gym->forceFill(['status' => 'submitted'])->save();
        $this->runReminders(0);
    }
}
