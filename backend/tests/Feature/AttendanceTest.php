<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\Membership;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\CheckInCode;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(10, 0));
    }

    private function scan(?string $code = null)
    {
        $code ??= app(CheckInCode::class)->issue($this->gym, $this->applicant)['payload'];

        return $this->actingAs($this->owner)->postJson($this->url('check-ins'), ['code' => $code]);
    }

    public function test_member_code_changes_each_minute_and_payload_checks_in_present(): void
    {
        $code = $this->actingAs($this->applicant)->getJson($this->url('my-check-in-code'))->assertOk()->json();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code['short_code']);
        $this->scan($code['payload'])->assertCreated()->assertJsonPath('check_in.attendance', 'present')->assertJsonPath('check_in.method', 'qr');
        $this->travel(1)->minutes();
        $this->assertNotSame($code['payload'], app(CheckInCode::class)->issue($this->gym, $this->applicant)['payload']);
    }

    public function test_grace_boundary_is_present_and_after_grace_is_late(): void
    {
        $this->travel(10)->minutes();
        $this->scan()->assertCreated()->assertJsonPath('check_in.attendance', 'present');
        $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'absent'])->assertOk();
        $this->travel(1)->seconds();
        $this->scan()->assertCreated()->assertJsonPath('check_in.attendance', 'late');
    }

    public function test_gym_grace_setting_is_used(): void
    {
        $this->gym->forceFill(['late_grace_minutes' => 20])->save();
        $this->travel(15)->minutes();
        $this->scan()->assertCreated()->assertJsonPath('check_in.attendance', 'present');
    }

    public function test_expired_wrong_gym_tampered_and_inactive_codes_are_refused(): void
    {
        $codes = app(CheckInCode::class);
        $old = $codes->issue($this->gym, $this->applicant, intdiv(now()->timestamp, 60) - 2);
        $this->scan($old['payload'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->scan($codes->issue(Gym::factory()->approved()->create(), $this->applicant)['payload'])->assertUnprocessable();
        $this->scan($codes->issue($this->gym, $this->applicant)['payload'].'x')->assertUnprocessable();
        Membership::withoutGlobalScope('gym')->where('user_id', $this->applicant->id)->update(['status' => 'expired']);
        $this->scan()->assertUnprocessable();
        $this->actingAs($this->applicant)->getJson($this->url('my-check-in-code'))->assertForbidden();
    }

    public function test_previous_window_short_code_and_account_check_in_work(): void
    {
        $code = app(CheckInCode::class)->issue($this->gym, $this->applicant, intdiv(now()->timestamp, 60) - 1);
        $this->scan($code['short_code'])->assertCreated();
        $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'absent'])->assertOk();
        $this->actingAs($this->owner)->getJson($this->url('check-ins/members?search=Ana%20Cruz'))->assertOk()->assertJsonPath('members.0.id', $this->applicant->id);
        $this->actingAs($this->owner)->postJson($this->url('check-ins'), ['user_id' => $this->applicant->id])
            ->assertCreated()->assertJsonPath('check_in.method', 'account');
    }

    public function test_member_and_training_view_cannot_write_attendance(): void
    {
        foreach ([$this->applicant, $this->coach] as $user) {
            $this->actingAs($user)->postJson($this->url('check-ins'), ['user_id' => $this->applicant->id])->assertForbidden();
            $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'present'])->assertForbidden();
            $this->postJson($this->url("bookings/{$this->bookingId}/check-out"))->assertForbidden();
        }
    }

    public function test_second_check_in_refused_and_check_out_keeps_time(): void
    {
        $this->scan()->assertCreated();
        $this->scan()->assertUnprocessable()->assertJsonPath('errors.code.0', 'Already checked in at 10:00 AM.');
        $this->travel(30)->minutes();
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$this->bookingId}/check-out"))
            ->assertOk()->assertJsonPath('attendance.check_out_at', '2026-11-09 10:30:00');
        $this->postJson($this->url("bookings/{$this->bookingId}/check-out"))->assertUnprocessable();
    }

    public function test_no_booking_in_window_creates_gym_visit_not_pack_debit(): void
    {
        $this->travel(-31)->minutes();
        $this->scan()->assertCreated()->assertJsonPath('check_in.attendance', 'gym_visit')->assertJsonPath('check_in.session', null);
        $this->assertDatabaseCount('gym_visits', 1);
        $this->assertDatabaseHas('session_bookings', ['id' => $this->bookingId, 'check_in_at' => null]);
    }

    public function test_session_pack_decrements_once_and_absent_restores_only_actual_debits(): void
    {
        $membership = Membership::withoutGlobalScope('gym')->where('user_id', $this->applicant->id)->firstOrFail();
        $membership->forceFill(['sessions_remaining' => 1])->save();
        $newer = $membership->replicate();
        $newer->forceFill(['sessions_remaining' => 99])->save();
        $this->scan()->assertCreated();
        $this->assertSame(0, $membership->fresh()->sessions_remaining);
        $this->assertSame(99, $newer->fresh()->sessions_remaining);
        $this->scan()->assertUnprocessable();
        $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'late'])->assertOk();
        $this->assertSame(0, $membership->fresh()->sessions_remaining);
        $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'absent'])->assertOk();
        $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'absent'])->assertOk();
        $this->assertSame(1, $membership->fresh()->sessions_remaining);
        $membership->forceFill(['sessions_remaining' => 0])->save();
        $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'present'])->assertOk();
        $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'absent'])->assertOk();
        $this->assertSame(0, $membership->fresh()->sessions_remaining);
    }

    public function test_manual_present_late_absent_record_staff_and_optional_time(): void
    {
        foreach (['present', 'late', 'absent'] as $attendance) {
            $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => $attendance, 'check_in_at' => '10:05'])
                ->assertOk()->assertJsonPath('attendance.attendance', $attendance)->assertJsonPath('attendance.method', 'manual');
            $this->assertDatabaseHas('session_bookings', [
                'id' => $this->bookingId, 'attendance_by' => $this->owner->id,
                'check_in_at' => $attendance === 'absent' ? null : '2026-11-09 10:05:00.000000',
            ]);
        }
    }

    public function test_todays_desk_uses_check_in_date_not_the_session_date(): void
    {
        $this->travel(1)->days();
        $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'present'])->assertOk();
        $this->getJson($this->url('check-ins'))->assertOk()->assertJsonCount(1, 'check_ins')->assertJsonPath('check_ins.0.booking_id', $this->bookingId);
        $this->travel(1)->days();
        $this->getJson($this->url('check-ins'))->assertOk()->assertJsonCount(0, 'check_ins');
    }

    public function test_manual_attendance_refuses_future_day_and_non_booked(): void
    {
        $this->travel(-1)->days();
        $this->actingAs($this->owner)->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'present'])->assertUnprocessable();
        $this->travel(1)->days();
        SessionBooking::withoutGlobalScope('gym')->findOrFail($this->bookingId)->forceFill(['status' => BookingStatus::Cancelled])->save();
        $this->putJson($this->url("bookings/{$this->bookingId}/attendance"), ['attendance' => 'present'])->assertUnprocessable();
    }

    public function test_no_show_command_only_marks_ended_unchecked_booked(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $original = SessionBooking::findOrFail($this->bookingId);
        foreach (['booked', 'requested', 'waitlisted', 'cancelled'] as $status) {
            $copy = $original->replicate();
            $copy->forceFill(['user_id' => User::factory()->create()->id, 'status' => $status])->save();
        }
        $checked = $original->replicate();
        $checked->forceFill(['user_id' => User::factory()->create()->id, 'check_in_at' => now()])->save();
        $absent = $original->replicate();
        $absent->forceFill(['user_id' => User::factory()->create()->id, 'attendance' => 'absent'])->save();
        $this->artisan('app:mark-no-shows')->assertSuccessful();
        $this->assertDatabaseHas('session_bookings', ['id' => $this->bookingId, 'attendance' => null]);
        $this->travel(61)->minutes();
        $this->app->forgetScopedInstances();
        $this->artisan('app:mark-no-shows')->expectsOutput('2 no-shows marked.')->assertSuccessful();
        $this->assertDatabaseHas('session_bookings', ['id' => $checked->id, 'attendance' => null]);
        $this->assertDatabaseHas('session_bookings', ['id' => $absent->id, 'attendance' => 'absent']);
    }

    public function test_no_show_command_runs_across_gyms_without_a_current_gym(): void
    {
        $first = $this->bookingId;
        $this->setUpBooking();
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(11, 1));
        $this->app->forgetScopedInstances();
        $this->artisan('app:mark-no-shows')->expectsOutput('2 no-shows marked.')->assertSuccessful();
        $this->assertDatabaseHas('session_bookings', ['id' => $first, 'attendance' => 'no_show']);
        $this->assertDatabaseHas('session_bookings', ['id' => $this->bookingId, 'attendance' => 'no_show']);
    }

    public function test_gym_visits_appear_in_history_but_not_in_session_or_class_filters(): void
    {
        $this->travel(-31)->minutes();
        $this->scan()->assertCreated();
        $this->actingAs($this->owner)->getJson($this->url('attendance?to=2026-11-09'))->assertOk()->assertJsonPath('attendance.0.attendance', 'gym_visit');
        $this->getJson($this->url("attendance?session_id={$this->sessionId}"))->assertOk()->assertJsonCount(0, 'attendance');
        $this->getJson($this->url('attendance?gym_service_id=99999'))->assertOk()->assertJsonCount(0, 'attendance');
    }

    public function test_check_in_and_attendance_cannot_cross_gym_boundaries(): void
    {
        $booking = $this->bookingId;
        $user = $this->applicant;
        $code = app(CheckInCode::class)->issue($this->gym, $user)['payload'];
        $this->setUpBooking();
        $this->actingAs($this->owner)->postJson($this->url('check-ins'), ['code' => $code])->assertUnprocessable();
        $this->postJson($this->url('check-ins'), ['user_id' => $user->id])->assertUnprocessable();
        $this->putJson($this->url("bookings/{$booking}/attendance"), ['attendance' => 'present'])->assertNotFound();
        $this->postJson($this->url("bookings/{$booking}/check-out"))->assertNotFound();
        $this->getJson($this->url("attendance?user_id={$user->id}"))->assertOk()->assertJsonCount(0, 'attendance');
    }

    public function test_history_filters_and_own_record_counts_do_not_expose_other_athletes(): void
    {
        $this->scan()->assertCreated();
        app(CurrentGym::class)->set($this->gym);
        $service = GymService::factory()->create();
        TrainingSession::findOrFail($this->sessionId)->update(['gym_service_id' => $service->id]);
        $other = SessionBooking::findOrFail($this->bookingId)->replicate();
        $other->forceFill(['user_id' => User::factory()->create()->id, 'attendance' => 'late'])->save();
        foreach (["session_id={$this->sessionId}", "gym_service_id={$service->id}", "user_id={$this->applicant->id}", 'from=2026-11-09&to=2026-11-09'] as $filter) {
            $this->actingAs($this->owner)->getJson($this->url('attendance?'.$filter))->assertOk()->assertJsonPath('attendance.0.session.id', $this->sessionId);
        }
        $this->getJson($this->url('attendance?session_id=999999'))->assertJsonCount(0, 'attendance');
        $this->getJson($this->url('attendance?gym_service_id=999999'))->assertJsonCount(0, 'attendance');
        $this->actingAs($this->applicant)->getJson($this->url('my-record'))->assertOk()
            ->assertJsonCount(1, 'record.attendance')->assertJsonPath('record.attendance_counts.present', 1)->assertJsonPath('record.attendance_counts.late', 0);
        $this->getJson($this->url('attendance'))->assertForbidden();
    }
}
