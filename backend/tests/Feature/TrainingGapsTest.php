<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\AthleteProfile;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\SessionChanged;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

/** D-24, D-32, D-35: gaps closed after the Phase 5 build. */
class TrainingGapsTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    private function levelSession(string $level): int
    {
        return $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData([
            'level' => $level, 'capacity' => 5, 'starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 11:00:00',
        ]))->assertCreated()->json('session.id');
    }

    private function mainSession(): TrainingSession
    {
        return TrainingSession::withoutGlobalScope('gym')->findOrFail($this->sessionId);
    }

    private function flaggedNotices(User $user): int
    {
        return $user->notifications()->where('type', SessionChanged::class)->where('data->type', 'flagged')->count();
    }

    // D-35

    public function test_level_must_be_at_or_above_session_level_and_unset_counts_as_beginner(): void
    {
        $intermediate = $this->levelSession('intermediate');
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$intermediate}/book"))
            ->assertUnprocessable()->assertSee('Intermediate athletes and above');

        $this->actingAs($this->owner)->putJson($this->url("athletes/{$this->applicant->id}/level"), ['level' => 'advanced'])
            ->assertOk()->assertJsonPath('level', 'advanced');
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$intermediate}/book"))->assertCreated();
        $this->actingAs($this->owner)->getJson($this->url("athletes/{$this->applicant->id}/record"))->assertJsonPath('record.level', 'advanced');
    }

    public function test_all_levels_session_is_open_to_a_beginner(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->assertNull(AthleteProfile::where('user_id', $this->applicant->id)->value('level'));
        $id = $this->levelSession('all');
        $this->actingAs($this->applicant)->postJson($this->url("timetable/{$id}/book"))->assertCreated();
    }

    public function test_setting_level_needs_permission_and_a_valid_level(): void
    {
        $viewer = $this->employeeWith(['training' => 'view']);
        $this->actingAs($viewer)->putJson($this->url("athletes/{$this->applicant->id}/level"), ['level' => 'advanced'])->assertForbidden();
        $this->actingAs($this->owner)->putJson($this->url("athletes/{$this->applicant->id}/level"), ['level' => 'all'])
            ->assertUnprocessable()->assertJsonValidationErrors('level');
        $this->putJson($this->url("athletes/{$this->applicant->id}/level"), ['level' => null])->assertOk()->assertJsonPath('level', null);
    }

    // D-32

    public function test_work_hours_change_flags_only_sessions_now_outside_hours_and_tells_athletes(): void
    {
        $tuesday = $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->sessionData([
            'starts_at' => '2026-11-10 10:00:00', 'ends_at' => '2026-11-10 11:00:00',
        ]))->assertCreated()->json('session.id');
        $week = collect(array_keys(config('hr.weekdays')))->map(fn ($day) => [
            'weekday' => $day, 'start_time' => $day === 'mon' ? null : '08:00', 'end_time' => $day === 'mon' ? null : '20:00',
        ])->all();

        $this->putJson($this->url("staff/{$this->coach->id}/schedule"), ['schedule' => $week])->assertOk();

        $this->assertNotNull($this->mainSession()->flagged_at);
        $this->assertStringContainsString('outside working hours', $this->mainSession()->flag_reason);
        $this->assertNull(TrainingSession::withoutGlobalScope('gym')->findOrFail($tuesday)->flagged_at);
        $this->assertSame(1, $this->flaggedNotices($this->applicant));
        $this->assertSame(1, $this->owner->notifications()->where('data->type', 'sessions_flagged')->count());
    }

    public function test_new_holiday_flags_sessions_that_day_and_clear_flag_waits_until_it_is_removed(): void
    {
        $holiday = $this->actingAs($this->owner)->postJson($this->url('holidays'), ['date' => '2026-11-09', 'name' => 'Founders Day'])
            ->assertCreated()->json('holiday.id');
        $this->assertSame('Holiday: Founders Day', $this->mainSession()->flag_reason);
        $this->assertSame(1, $this->flaggedNotices($this->applicant));

        $this->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertUnprocessable()->assertJsonValidationErrors('flag');

        $this->deleteJson($this->url("holidays/{$holiday}"))->assertOk();
        $this->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertOk()->assertJsonPath('session.flagged_at', null);
        $this->assertTrue($this->applicant->notifications()->where('data->type', 'flag_cleared')->exists());
        $this->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertStatus(409);
    }

    public function test_clearing_flag_promotes_waiting_list(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Member);
        $membership = $this->membership(SessionBooking::findOrFail($this->bookingId)->membership_id)->replicate();
        $membership->user_id = $other->id;
        $membership->save();
        $waiting = $this->actingAs($other)->postJson($this->url("timetable/{$this->sessionId}/book"))->assertCreated()->json('booking.id');

        $this->mainSession()->update(['flagged_at' => now(), 'flag_reason' => 'Review']);
        $this->actingAs($this->owner)->postJson($this->url("bookings/{$this->bookingId}/cancel"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $waiting, 'status' => 'waitlisted']);

        $this->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertOk();
        $this->assertDatabaseHas('session_bookings', ['id' => $waiting, 'status' => 'booked']);
    }

    public function test_removing_coach_flags_their_sessions_and_flag_cannot_be_cleared(): void
    {
        $this->actingAs($this->owner)->deleteJson($this->url("staff/{$this->coach->id}"))->assertOk();
        $this->assertStringContainsString('no longer on staff', $this->mainSession()->flag_reason);
        $this->assertSame(1, $this->flaggedNotices($this->applicant));
        $this->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertUnprocessable()->assertJsonValidationErrors('coach_id');
    }

    public function test_clear_flag_needs_manage_permission(): void
    {
        $this->mainSession()->update(['flagged_at' => now(), 'flag_reason' => 'Review']);
        $this->actingAs($this->coach)->postJson($this->url("sessions/{$this->sessionId}/clear-flag"))->assertForbidden();
    }

    // D-24

    public function test_group_session_and_gym_types_can_be_used_by_sessions_and_services(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), ['custom_activity_types' => ['Kids class', 'Strength & conditioning']])
            ->assertOk()->assertJsonPath('setup.custom_activity_types', ['Kids class', 'Strength & conditioning'])
            ->assertJsonPath('setup.options.session_types.custom_kids_class', 'Kids class');

        $this->getJson($this->url('sessions/options'))->assertOk()
            ->assertJsonPath('options.activity_types.group_session', 'Group session')
            ->assertJsonPath('options.activity_types.custom_strength_conditioning', 'Strength & conditioning');

        foreach (['group_session', 'custom_kids_class'] as $index => $type) {
            $this->postJson($this->url('sessions'), $this->sessionData([
                'type' => $type, 'starts_at' => "2026-11-1{$index} 15:00:00", 'ends_at' => "2026-11-1{$index} 16:00:00",
            ]))->assertCreated()->assertJsonPath('session.type', $type);
        }
        $this->postJson($this->url('sessions'), $this->sessionData(['type' => 'custom_unknown']))->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->actingAs($this->applicant)->getJson($this->url('timetable?from=2026-11-11&to=2026-11-11'))
            ->assertOk()->assertSee('Kids class');
    }

    public function test_gym_type_names_must_be_new_and_different(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), ['custom_activity_types' => ['Sparring']])
            ->assertUnprocessable()->assertJsonValidationErrors('custom_activity_types.0');
        $this->putJson($this->url('setup'), ['custom_activity_types' => ['Kids class', 'kids class']])
            ->assertUnprocessable();
        $this->putJson($this->url('setup'), ['custom_activity_types' => ['Kids class', 'Kids-class']])
            ->assertUnprocessable()->assertJsonValidationErrors('custom_activity_types');
    }
}
