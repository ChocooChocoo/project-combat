<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\SessionChanged;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class SessionChangeTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    public function test_move_notifies_athlete_and_coach_but_text_edit_does_not(): void
    {
        $this->actingAs($this->owner)->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['name' => 'New name']))->assertOk();
        $this->assertSame(0, $this->coach->notifications()->count());
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['starts_at' => '2026-11-09 12:00:00', 'ends_at' => '2026-11-09 13:00:00']))->assertOk();
        foreach ([$this->applicant, $this->coach] as $user) {
            $notice = $user->notifications()->where('type', SessionChanged::class)->firstOrFail();
            $this->assertSame('moved', $notice->data['type']);
            $this->assertStringNotContainsString('Room', $notice->data['message']);
            $this->assertStringContainsString('→', $notice->data['message']);
        }
    }

    public function test_move_into_clash_is_refused(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData(['starts_at' => '2026-11-09 12:00:00', 'ends_at' => '2026-11-09 13:00:00']))->assertCreated();
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['starts_at' => '2026-11-09 12:00:00', 'ends_at' => '2026-11-09 13:00:00']))->assertUnprocessable();
        $this->assertSame(0, $this->coach->notifications()->count());
    }

    public function test_room_only_move_does_not_repeat_unchanged_time(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $room = Room::factory()->create();
        $this->actingAs($this->owner)->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['room_id' => $room->id]))->assertOk();
        $notice = $this->applicant->notifications()->where('type', SessionChanged::class)->firstOrFail();
        $this->assertSame("Boxing: Room {$this->room->name} → {$room->name}.", $notice->data['message']);
    }

    public function test_move_and_cancellation_include_requests_and_waiting_list_not_rejected(): void
    {
        $users = [];
        foreach (['requested', 'waitlisted', 'rejected'] as $status) {
            $user = User::factory()->create();
            $booking = SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->replicate();
            $booking->forceFill(['user_id' => $user->id, 'status' => $status])->save();
            $users[$status] = $user;
        }
        $this->actingAs($this->owner)->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['capacity' => 2, 'starts_at' => '2026-11-09 12:00:00', 'ends_at' => '2026-11-09 13:00:00']))->assertOk();
        $this->postJson($this->url("sessions/{$this->sessionId}/cancel"), ['reason' => 'Closed'])->assertOk();
        foreach (['requested', 'waitlisted'] as $status) {
            $this->assertSame(2, $users[$status]->notifications()->where('type', SessionChanged::class)->count());
            $this->assertDatabaseHas('session_bookings', ['user_id' => $users[$status]->id, 'status' => 'cancelled']);
        }
        $this->assertSame(0, $users['rejected']->notifications()->count());
    }

    public function test_capacity_increase_promotes_and_cannot_drop_below_taken(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Member);
        $membership = $this->membership(SessionBooking::findOrFail($this->bookingId)->membership_id)->replicate();
        $membership->user_id = $other->id;
        $membership->save();
        $id = $this->actingAs($other)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertCreated()->assertJsonPath('booking.status', 'waitlisted')->json('booking.id');
        $this->actingAs($this->owner)->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['capacity' => 2]))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $id, 'status' => 'booked']);
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData())->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }

    public function test_cancel_requires_reason_permission_and_refunds_each_payment_once(): void
    {
        $this->bookingPayment(['amount' => 50])->assertCreated();
        $this->bookingPayment(['amount' => 100, 'method' => 'card'])->assertCreated();
        $this->actingAs($this->coach)->postJson($this->url("sessions/{$this->sessionId}/cancel"), ['reason' => 'Closed'])->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url("sessions/{$this->sessionId}/cancel"))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url("sessions/{$this->sessionId}/cancel"), ['reason' => 'Closed'])->assertOk()->assertJsonPath('session.status', 'cancelled');
        $this->assertDatabaseHas('session_bookings', ['id' => $this->bookingId, 'status' => 'cancelled', 'cancelled_by' => $this->owner->id]);
        $this->bookingPayload()->assertJsonCount(0, 'upcoming')->assertJsonPath('past.0.paid', '0.00')->assertJsonPath('past.0.payment_state', 'refunded')->assertJsonCount(4, 'past.0.payments');
        $this->assertSame(2, $this->applicant->notifications()->where('data->title', 'Refund recorded')->count());
        $this->assertSame(2, Payment::withoutGlobalScope('gym')->where('session_booking_id', $this->bookingId)->whereNotNull('corrects_id')->count());
        foreach (Payment::withoutGlobalScope('gym')->where('session_booking_id', $this->bookingId)->whereNotNull('corrects_id')->get() as $refund) {
            $original = Payment::withoutGlobalScope('gym')->findOrFail($refund->corrects_id);
            $this->assertSame($original->method, $refund->method);
            $this->assertSame(-(float) $original->amount, (float) $refund->amount);
            $this->assertStringContainsString('staff hand the money back', $refund->note);
        }
        foreach ([$this->applicant, $this->coach] as $user) {
            $this->assertTrue($user->notifications()->where('type', SessionChanged::class)->where('data->type', 'cancelled')->exists());
        }
        $this->actingAs($this->owner)->postJson($this->url("sessions/{$this->sessionId}/cancel"), ['reason' => 'Again'])->assertStatus(409);
    }

    public function test_leave_flags_only_scheduled_sessions_in_range_and_notifies_managers_once(): void
    {
        $manager = $this->employeeWith(['training' => 'manage']);
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData(['starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 11:00:00']))->assertCreated();
        app(CurrentGym::class)->set($this->gym);
        $leave = LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => 'pending']);
        $this->actingAs($this->owner)->postJson($this->url("leave-requests/{$leave->id}/decision"), ['decision' => 'approve'])->assertOk();
        $this->getJson($this->url('sessions?flagged=1'))->assertOk()->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.id', $this->sessionId)
            ->assertJsonPath('sessions.0.flag_reason', 'Coach on approved leave (Nov 9, 2026)');
        foreach ([$this->owner, $manager] as $user) {
            $this->assertSame(1, $user->notifications()->where('data->type', 'sessions_flagged')->count());
            $this->assertStringStartsWith('1 session needs', $user->notifications()->where('data->type', 'sessions_flagged')->firstOrFail()->data['message']);
        }
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('staff review');
    }

    public function test_reassign_only_to_free_coach_clears_flag_and_notifies_both_coaches(): void
    {
        $free = $this->freeCoach();
        $busy = $this->freeCoach();
        app(CurrentGym::class)->set($this->gym);
        $room = Room::factory()->create();
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData(['coach_id' => $busy->id, 'room_id' => $room->id]))->assertCreated();
        TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId)->update(['flagged_at' => now(), 'flag_reason' => 'Review']);
        $coaches = $this->getJson($this->url("sessions/{$this->sessionId}/free-coaches"))->assertOk()->json('coaches');
        $this->assertContains($free->id, array_column($coaches, 'id'));
        $this->assertNotContains($busy->id, array_column($coaches, 'id'));
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['coach_id' => $busy->id]))->assertUnprocessable();
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['coach_id' => $free->id]))->assertOk()->assertJsonPath('session.flagged_at', null);
        foreach ([$this->applicant, $this->coach, $free] as $user) {
            $this->assertTrue($user->notifications()->where('type', SessionChanged::class)->where('data->type', 'coach_changed')->exists());
        }
    }

    public function test_text_edit_keeps_leave_flag_but_moving_outside_leave_clears_it(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $leave = LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => 'pending']);
        $this->actingAs($this->owner)->postJson($this->url("leave-requests/{$leave->id}/decision"), ['decision' => 'approve'])->assertOk();
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['name' => 'Updated title']))->assertOk();
        $this->assertNotNull(TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId)->flagged_at);
        $this->putJson($this->url("sessions/{$this->sessionId}"), $this->sessionData(['starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 11:00:00']))
            ->assertOk()->assertJsonPath('session.flagged_at', null)->assertJsonPath('session.flag_reason', null);
        $this->assertTrue($this->applicant->notifications()->where('type', SessionChanged::class)->where('data->type', 'moved')->exists());
    }
}
