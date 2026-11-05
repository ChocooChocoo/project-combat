<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\LeaveRequestStatus;
use App\Enums\MembershipStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\GymService;
use App\Models\LeaveRequest;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Room;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-44, R-47 to R-50: booking rules run through the real gym routes. */
class BookingTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    private User $coach;

    private Room $room;

    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(9, 0));
        $this->setUpMembershipGym();
        $this->coach = $this->employeeWith(['training' => 'view']);
        app(CurrentGym::class)->set($this->gym);
        (new EmployeeProfile)->forceFill(['user_id' => $this->coach->id, 'coach_rank' => 'Coach'])->save();
        $this->room = Room::factory()->create();
        foreach (array_keys(config('hr.weekdays')) as $weekday) {
            WorkSchedule::create(['user_id' => $this->coach->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '20:00']);
        }
        $this->sessionId = TrainingSession::create([
            'name' => 'Boxing Basics', 'type' => 'classes', 'level' => 'all', 'discipline' => 'boxing',
            'coach_id' => $this->coach->id, 'room_id' => $this->room->id,
            'starts_at' => '2026-11-09 10:00:00', 'ends_at' => '2026-11-09 11:00:00',
            'capacity' => 1, 'fee' => 150, 'content' => 'Pad work', 'requirements' => 'Wraps', 'bring' => ['Water'],
        ])->id;
        $this->gym->addPerson($this->applicant, GymPersonType::Member);
        $this->app->forgetScopedInstances();
    }

    private function changeSession(array $changes): void
    {
        TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId)->update($changes);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Member);
        app(CurrentGym::class)->set($this->gym);
        $membership = new Membership([
            'user_id' => $user->id, 'membership_plan_id' => $this->plan->id,
            'emergency_contacts' => [], 'health_answers' => [], 'waivers' => [],
            'signature_name' => 'Test athlete', 'signed_on' => today(),
        ]);
        $membership->forceFill(['status' => MembershipStatus::Active])->save();
        $this->app->forgetScopedInstances();

        return $user;
    }

    private function book(User $user, string $status = 'booked'): int
    {
        return $this->actingAs($user)->postJson($this->url("timetable/{$this->sessionId}/book"))
            ->assertCreated()->assertJsonPath('booking.status', $status)->json('booking.id');
    }

    public function test_no_active_membership_is_refused_with_reason(): void
    {
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$this->sessionId}/book"))
            ->assertUnprocessable()->assertJsonValidationErrors('eligibility')->assertSee('active membership');
    }

    public function test_wrong_plan_and_exhausted_membership_are_refused(): void
    {
        $membership = $this->active();
        $other = MembershipPlan::factory()->forGym($this->gym)->create();
        $this->changeSession(['allowed_plan_ids' => [$other->id]]);
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$this->sessionId}/book"))
            ->assertUnprocessable()->assertSee('plan');
        $this->changeSession(['allowed_plan_ids' => []]);
        $membership->forceFill(['sessions_remaining' => 0])->save();
        $this->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('remaining');
    }

    public function test_booking_snapshots_fee_and_notifies_athlete(): void
    {
        $user = $this->member();
        $id = $this->book($user);
        $this->assertDatabaseHas('session_bookings', ['id' => $id, 'fee' => 150, 'gym_id' => $this->gym->id]);
        $this->assertSame('booked', $user->notifications()->first()->data['type']);
        $this->changeSession(['fee' => 200]);
        $this->assertDatabaseHas('session_bookings', ['id' => $id, 'fee' => 150]);
    }

    public function test_requested_holds_place_and_approvers_are_notified(): void
    {
        $this->gym->update(['booking_approval' => true]);
        $approver = $this->employeeWith(['training' => 'approve']);
        $id = $this->book($this->member(), 'requested');
        $this->assertSame($id, $this->owner->notifications()->first()->data['booking_id']);
        $this->assertSame($id, $approver->notifications()->first()->data['booking_id']);
        $this->assertSame(0, $this->coach->notifications()->count());
        $this->book($this->member(), 'waitlisted');
    }

    public function test_session_false_override_beats_gym_approval(): void
    {
        $this->gym->update(['booking_approval' => true]);
        $this->changeSession(['needs_approval' => false]);
        $this->book($this->member());
    }

    public function test_approve_and_reject_notify_athlete_and_require_approval_permission(): void
    {
        $this->changeSession(['needs_approval' => true]);
        $user = $this->member();
        $id = $this->book($user, 'requested');
        $this->actingAs($this->coach)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'approve'])->assertOk()->assertJsonPath('booking.status', 'booked');
        $this->assertTrue($user->notifications()->where('data->type', 'approved')->exists());
        $this->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'reject', 'reason' => 'Not suitable'])->assertUnprocessable();
        $this->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertSame($id, $this->book($user, 'requested'));
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'reject', 'reason' => 'Not suitable'])
            ->assertOk()->assertJsonPath('booking.decision_reason', 'Not suitable');
        $this->assertTrue($user->notifications()->where('data->type', 'rejected')->exists());
    }

    public function test_last_place_and_waiting_list_order_then_automatic_promotion(): void
    {
        // Sequential requests prove counting; MySQL's session-row lock serializes concurrent requests.
        $first = $this->member();
        $second = $this->member();
        $third = $this->member();
        $id = $this->book($first);
        $secondId = $this->book($second, 'waitlisted');
        $this->book($third, 'waitlisted');
        $this->actingAs($first)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $secondId, 'status' => 'booked']);
        $this->assertTrue($second->notifications()->where('data->type', 'promoted')->exists());
        $this->assertDatabaseHas('session_bookings', ['user_id' => $third->id, 'status' => 'waitlisted']);
        $this->actingAs($this->owner)->getJson($this->url("sessions/{$this->sessionId}/roster"))
            ->assertOk()->assertJsonCount(3, 'bookings')->assertJsonPath('bookings.0.id', $secondId);
    }

    public function test_reject_promotes_next_to_requested_using_approval_rule(): void
    {
        $this->gym->update(['booking_approval' => true]);
        $id = $this->book($this->member(), 'requested');
        $next = $this->member();
        $nextId = $this->book($next, 'waitlisted');
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'reject', 'reason' => 'Try another class'])->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $nextId, 'status' => 'requested']);
        $this->assertTrue($next->notifications()->where('data->type', 'promoted')->exists());
    }

    public function test_waitlisted_can_leave_and_rejoin_at_back_reusing_row(): void
    {
        $this->book($this->member());
        $second = $this->member();
        $id = $this->book($second, 'waitlisted');
        $third = $this->member();
        $thirdId = $this->book($third, 'waitlisted');
        $this->actingAs($second)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertSame($id, $this->book($second, 'waitlisted'));
        $this->actingAs($this->owner)->getJson($this->url("sessions/{$this->sessionId}/roster"))
            ->assertOk()->assertJsonPath('bookings.1.id', $thirdId)->assertJsonPath('bookings.2.id', $id);
    }

    public function test_cutoff_and_staff_cancellation_and_ownership(): void
    {
        $user = $this->member();
        $id = $this->book($user);
        $stranger = $this->member();
        $this->actingAs($stranger)->postJson($this->url("bookings/{$id}/cancel"))->assertForbidden();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 1));
        $this->actingAs($user)->postJson($this->url("bookings/{$id}/cancel"))->assertUnprocessable()->assertSee('cut-off');
        $this->actingAs($this->coach)->postJson($this->url("bookings/{$id}/cancel"))->assertForbidden();
        $manager = $this->employeeWith(['training' => 'manage']);
        $this->actingAs($manager)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $id, 'cancelled_by' => $manager->id]);
    }

    public function test_cannot_book_past_cancelled_flagged_or_duplicate_session(): void
    {
        $user = $this->member();
        $this->changeSession(['status' => 'cancelled']);
        $this->actingAs($user)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable();
        $this->changeSession(['status' => 'scheduled', 'flagged_at' => now()]);
        $this->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable();
        $this->changeSession(['flagged_at' => null]);
        $this->book($user);
        $this->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('already');
        $this->travelTo(now()->setDate(2026, 11, 10));
        $this->actingAs($this->member())->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('started');
    }

    public function test_coach_leave_and_changed_work_hours_refuse_booking(): void
    {
        $user = $this->member();
        app(CurrentGym::class)->set($this->gym);
        $leave = LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => LeaveRequestStatus::Approved]);
        $this->actingAs($user)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('approved leave');
        $leave->delete();
        WorkSchedule::withoutGlobalScope('gym')->where('user_id', $this->coach->id)->where('weekday', 'mon')->update(['start_time' => '12:00']);
        $this->postJson($this->url("timetable/{$this->sessionId}/book"))->assertUnprocessable()->assertSee('working hours');
    }

    public function test_timetable_details_include_profile_repeat_places_and_own_booking(): void
    {
        $user = $this->member();
        app(CurrentGym::class)->set($this->gym);
        EmployeeProfile::where('user_id', $this->coach->id)->firstOrFail()->forceFill(['coach_public' => true, 'coach_bio' => 'Boxing coach', 'coach_rank' => 'Senior'])->save();
        $this->changeSession(['series_id' => 'weekly']);
        $copy = TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId)->replicate();
        $copy->starts_at = '2026-11-11 10:00:00';
        $copy->ends_at = '2026-11-11 11:00:00';
        $copy->save();
        $id = $this->book($user);
        $this->getJson($this->url('timetable'))->assertOk()->assertJsonCount(2, 'sessions')->assertJsonPath('sessions.0.places_left', 0);
        $this->getJson($this->url("timetable/{$this->sessionId}"))->assertOk()
            ->assertJsonPath('session.coach.bio', 'Boxing coach')->assertJsonPath('session.repeat_note', 'Repeats: Mon, Wed')
            ->assertJsonPath('session.duration_minutes', 60)->assertJsonPath('session.waiting_list_open', true)
            ->assertJsonPath('session.my_booking.id', $id)->assertJsonPath('session.eligibility_reasons', []);
        $this->getJson($this->url('timetable?from=2026-11-10&to=2026-11-11'))->assertOk()->assertJsonCount(1, 'sessions');
        $this->getJson($this->url('timetable?from=2026-11-12&to=2026-11-10'))->assertUnprocessable();
    }

    public function test_hidden_coach_profile_is_not_exposed_to_athletes(): void
    {
        $user = $this->member();
        app(CurrentGym::class)->set($this->gym);
        EmployeeProfile::where('user_id', $this->coach->id)->firstOrFail()->forceFill(['coach_public' => false, 'coach_bio' => 'Private draft'])->save();
        $this->actingAs($user)->getJson($this->url("timetable/{$this->sessionId}"))->assertOk()->assertDontSee('Private draft');
    }

    private function privateService(): GymService
    {
        app(CurrentGym::class)->set($this->gym);

        return GymService::factory()->create(['activity_type' => 'private_coaching', 'duration_minutes' => 60, 'price' => 500, 'price_included' => false]);
    }

    public function test_open_slots_exclude_clashes_past_and_outside_hours(): void
    {
        $service = $this->privateService();
        $this->actingAs($this->member())->getJson($this->url("coaches/{$this->coach->id}/open-slots?date=2026-11-09&service_id={$service->id}"))
            ->assertOk()->assertJsonPath('slots.0.starts_at', '2026-11-09 08:00:00')
            ->assertJsonMissing(['starts_at' => '2026-11-09 09:30:00'])
            ->assertJsonMissing(['starts_at' => '2026-11-09 10:00:00'])
            ->assertJsonMissing(['starts_at' => '2026-11-09 19:30:00']);
        $this->getJson($this->url("coaches/{$this->coach->id}/open-slots?date=2026-11-04&service_id={$service->id}"))->assertOk()->assertJsonCount(0, 'slots');
    }

    public function test_private_booking_rechecks_slot_creates_capacity_one_and_blocks_second_request(): void
    {
        $service = $this->privateService();
        $body = ['gym_service_id' => $service->id, 'coach_id' => $this->coach->id, 'starts_at' => '2026-11-09 11:00:00'];
        $this->actingAs($this->member())->postJson($this->url('private-bookings'), $body)->assertCreated()
            ->assertJsonPath('session.type', 'private_coaching')->assertJsonPath('session.capacity', 1)->assertJsonPath('booking.fee', '500.00');
        $this->actingAs($this->member())->postJson($this->url('private-bookings'), $body)->assertUnprocessable();
        $this->assertDatabaseCount('training_sessions', 2);
    }

    public function test_private_slots_require_free_active_room_and_valid_coach_and_service(): void
    {
        $service = $this->privateService();
        $user = $this->member();
        $this->room->update(['is_active' => false]);
        $this->actingAs($user)->getJson($this->url("coaches/{$this->coach->id}/open-slots?date=2026-11-09&service_id={$service->id}"))->assertOk()->assertJsonCount(0, 'slots');
        $this->getJson($this->url("coaches/{$user->id}/open-slots?date=2026-11-09&service_id={$service->id}"))->assertNotFound();
        $service->update(['activity_type' => 'classes']);
        $this->getJson($this->url("coaches/{$this->coach->id}/open-slots?date=2026-11-09&service_id={$service->id}"))->assertUnprocessable();
    }

    public function test_private_slot_excludes_room_clashes_leave_and_holidays(): void
    {
        $service = $this->privateService();
        $user = $this->member();
        $this->changeSession(['coach_id' => $this->owner->id]);
        $path = $this->url("coaches/{$this->coach->id}/open-slots?date=2026-11-09&service_id={$service->id}");
        $this->actingAs($user)->getJson($path)->assertOk()->assertJsonMissing(['starts_at' => '2026-11-09 10:00:00']);
        app(CurrentGym::class)->set($this->gym);
        $room = Room::factory()->create();
        $this->getJson($path)->assertOk()->assertJsonFragment(['starts_at' => '2026-11-09 10:00:00']);
        $this->postJson($this->url('private-bookings'), ['gym_service_id' => $service->id, 'coach_id' => $this->coach->id, 'starts_at' => '2026-11-09 10:00:00'])
            ->assertCreated()->assertJsonPath('session.room.id', $room->id);
        app(CurrentGym::class)->set($this->gym);
        $leave = LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => LeaveRequestStatus::Approved]);
        $this->getJson($path)->assertOk()->assertJsonCount(0, 'slots');
        $leave->delete();
        GymHoliday::create(['date' => '2026-11-09', 'name' => 'Closed']);
        $this->getJson($path)->assertOk()->assertJsonCount(0, 'slots');
    }

    public function test_private_booking_rolls_back_without_membership_and_honours_included_price(): void
    {
        $service = $this->privateService();
        $body = ['gym_service_id' => $service->id, 'coach_id' => $this->coach->id, 'starts_at' => '2026-11-09 11:00:00'];
        $this->actingAs($this->applicant)->postJson($this->url('private-bookings'), $body)->assertUnprocessable()->assertSee('active membership');
        $this->assertDatabaseCount('training_sessions', 1);
        $service->update(['price_included' => true]);
        $this->actingAs($this->member())->postJson($this->url('private-bookings'), $body)->assertCreated()->assertJsonPath('booking.fee', '0.00');
    }

    public function test_cutoff_boundary_is_staff_only_and_staff_can_cancel_after_start(): void
    {
        $user = $this->member();
        $id = $this->book($user);
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(8, 0));
        $this->actingAs($user)->postJson($this->url("bookings/{$id}/cancel"))->assertUnprocessable();
        $this->travelTo(now()->setTime(11, 0));
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
    }

    public function test_training_view_cannot_reject_and_approval_rechecks_membership(): void
    {
        $this->changeSession(['needs_approval' => true]);
        $user = $this->member();
        $id = $this->book($user, 'requested');
        $this->actingAs($this->coach)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'reject', 'reason' => 'No'])->assertForbidden();
        Membership::withoutGlobalScope('gym')->where('user_id', $user->id)->update(['status' => MembershipStatus::Frozen]);
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$id}/decision"), ['decision' => 'approve'])->assertUnprocessable()->assertSee('active membership');
        $this->assertDatabaseHas('session_bookings', ['id' => $id, 'status' => 'requested']);
    }

    public function test_ineligible_waiter_is_not_promoted_but_notified_and_next_gets_place(): void
    {
        $first = $this->member();
        $id = $this->book($first);
        $second = $this->member();
        $secondId = $this->book($second, 'waitlisted');
        $thirdId = $this->book($this->member(), 'waitlisted');
        Membership::withoutGlobalScope('gym')->where('user_id', $second->id)->update(['status' => MembershipStatus::Frozen]);
        $this->actingAs($first)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $secondId, 'status' => 'rejected']);
        $this->assertDatabaseHas('session_bookings', ['id' => $thirdId, 'status' => 'booked']);
        $this->assertTrue($second->notifications()->where('data->type', 'rejected')->exists());
    }

    public function test_coach_unavailability_does_not_erase_waiting_list_on_cancellation(): void
    {
        $first = $this->member();
        $id = $this->book($first);
        $nextId = $this->book($this->member(), 'waitlisted');
        app(CurrentGym::class)->set($this->gym);
        LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => LeaveRequestStatus::Approved]);
        $this->actingAs($first)->postJson($this->url("bookings/{$id}/cancel"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $nextId, 'status' => 'waitlisted']);
    }

    public function test_coach_calendar_shows_hours_leave_holidays_and_sessions_with_access_checks(): void
    {
        app(CurrentGym::class)->set($this->gym);
        GymHoliday::create(['date' => '2026-11-10', 'name' => 'Gym day']);
        LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => LeaveRequestStatus::Approved]);
        $path = $this->url("coaches/{$this->coach->id}/calendar?from=2026-11-09&to=2026-11-15");
        $this->actingAs($this->coach)->getJson($path)->assertOk()->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.approved_leave', true)->assertJsonCount(1, 'days.0.sessions')->assertJsonPath('days.1.holidays.0.name', 'Gym day');
        $this->actingAs($this->employeeWith(['hr' => 'view']))->getJson($path)->assertOk();
        $this->actingAs($this->member())->getJson($path)->assertForbidden();
        $this->actingAs($this->owner)->getJson($this->url("coaches/{$this->coach->id}/calendar?from=2027-01-01&to=2027-01-01"))
            ->assertOk()->assertJsonPath('days.0.holidays.0.national', true);
    }

    public function test_staff_requested_list_and_cross_gym_my_bookings_and_isolation(): void
    {
        $user = $this->member();
        $this->changeSession(['needs_approval' => true]);
        $id = $this->book($user, 'requested');
        $this->actingAs($this->coach)->getJson($this->url('bookings?status=requested'))->assertOk()->assertJsonPath('bookings.0.id', $id);
        $this->actingAs($user)->getJson($this->url('bookings'))->assertForbidden();
        $other = Gym::factory()->approved()->create();
        $other->addPerson($user, GymPersonType::Member);
        $this->getJson("/api/gyms/{$other->id}/timetable/{$this->sessionId}")->assertNotFound();
        $this->postJson("/api/gyms/{$other->id}/bookings/{$id}/cancel")->assertNotFound();
        app(CurrentGym::class)->set($other);
        $copy = TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId)->replicate();
        $copy->gym_id = $other->id;
        $copy->room_id = Room::factory()->create()->id;
        $copy->starts_at = '2026-11-01 10:00:00';
        $copy->ends_at = '2026-11-01 11:00:00';
        $copy->save();
        $booking = new SessionBooking;
        $booking->forceFill(['training_session_id' => $copy->id, 'user_id' => $user->id, 'status' => 'booked'])->save();
        $this->app->forgetScopedInstances();
        $this->getJson('/api/my-bookings')->assertOk()->assertJsonCount(1, 'upcoming')->assertJsonCount(1, 'past')
            ->assertJsonPath('upcoming.0.gym.name', $this->gym->name)->assertJsonPath('past.0.gym.name', $other->name);
        $this->actingAs($this->owner)->getJson('/api/my-bookings')->assertOk()->assertJsonCount(0, 'upcoming')->assertJsonCount(0, 'past');
    }

    public function test_timetable_with_only_to_before_today_is_invalid_and_past_cancelled_full_session_has_no_open_waiting_list(): void
    {
        $user = $this->member();
        $this->actingAs($user)->getJson($this->url('timetable?to=2026-11-01'))->assertUnprocessable();
        $this->book($user);
        $this->changeSession(['status' => 'cancelled']);
        $this->getJson($this->url("timetable/{$this->sessionId}"))->assertOk()->assertJsonPath('session.waiting_list_open', false);
    }
}
