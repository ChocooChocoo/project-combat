<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\LeaveRequestStatus;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\GymService;
use App\Models\LeaveRequest;
use App\Models\Room;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-42, R-43: complete sessions and atomic scheduling checks. */
class SessionTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    private User $coach;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
        $this->coach = $this->employeeWith(['training' => 'view']);
        app(CurrentGym::class)->set($this->gym);
        (new EmployeeProfile)->forceFill(['user_id' => $this->coach->id, 'coach_rank' => 'Coach'])->save();
        $this->room = Room::factory()->create();
        foreach (array_keys(config('hr.weekdays')) as $weekday) {
            WorkSchedule::create(['user_id' => $this->coach->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '20:00']);
        }
        $this->app->forgetScopedInstances();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'classes', 'name' => 'Boxing Basics', 'discipline' => 'boxing', 'level' => 'all',
            'coach_id' => $this->coach->id, 'room_id' => $this->room->id,
            'starts_at' => '2026-11-09 10:00:00', 'ends_at' => '2026-11-09 11:00:00',
            'capacity' => 12, 'allowed_plan_ids' => [$this->plan->id], 'content' => 'Pad work',
            'requirements' => 'Bring wraps', 'bring' => ['Water'], 'fee' => 150, 'needs_approval' => null,
        ], $overrides);
    }

    private function createSession(array $overrides = []): int
    {
        return $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload($overrides))
            ->assertCreated()->json('session.id');
    }

    public function test_owner_creates_each_type_with_service_and_details(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $service = GymService::factory()->create();
        foreach (array_keys(config('offer.activity_types')) as $index => $type) {
            $hour = 10 + $index;
            $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload([
                'type' => $type, 'gym_service_id' => $service->id,
                'starts_at' => "2026-11-09 {$hour}:00:00", 'ends_at' => '2026-11-09 '.($hour + 1).':00:00',
            ]))->assertCreated()->assertJsonPath('session.type', $type)
                ->assertJsonPath('session.service.id', $service->id)->assertJsonPath('session.coach.id', $this->coach->id)
                ->assertJsonPath('session.room.id', $this->room->id)->assertJsonPath('session.content', 'Pad work')
                ->assertJsonPath('session.allowed_plan_ids', [$this->plan->id])->assertJsonPath('session.fee', '150.00');
        }
        $this->assertDatabaseCount('training_sessions', count(config('offer.activity_types')));
    }

    public function test_training_manage_can_create_but_view_cannot(): void
    {
        $manager = $this->employeeWith(['training' => 'manage']);
        $this->actingAs($manager)->postJson($this->url('sessions'), $this->payload())->assertCreated();
        $this->actingAs($this->coach)->postJson($this->url('sessions'), $this->payload())->assertForbidden();
        $this->getJson($this->url('sessions?from=2026-11-09&to=2026-11-15'))->assertOk()->assertJsonCount(1, 'sessions');
    }

    public function test_required_fields_and_time_limits_are_validated(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('sessions'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'level', 'coach_id', 'room_id', 'starts_at', 'ends_at', 'capacity']);
        foreach (['2026-11-09 09:00:00', '2026-11-10 11:00:00'] as $end) {
            $this->postJson($this->url('sessions'), $this->payload(['ends_at' => $end]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        }
    }

    public function test_coach_clash_names_the_conflicting_session(): void
    {
        $this->createSession();
        app(CurrentGym::class)->set($this->gym);
        $room = Room::factory()->create();
        $this->postJson($this->url('sessions'), $this->payload(['room_id' => $room->id]))->assertUnprocessable()
            ->assertJsonValidationErrors('coach_id')->assertSee('Boxing Basics');
        $this->assertDatabaseCount('training_sessions', 1);
    }

    public function test_room_clash_is_refused(): void
    {
        $this->createSession();
        $other = $this->employeeWith(['training' => 'manage']);
        app(CurrentGym::class)->set($this->gym);
        (new EmployeeProfile)->forceFill(['user_id' => $other->id, 'coach_rank' => 'Coach'])->save();
        WorkSchedule::create(['user_id' => $other->id, 'weekday' => 'mon', 'start_time' => '08:00', 'end_time' => '20:00']);
        $this->postJson($this->url('sessions'), $this->payload(['coach_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    public function test_outside_work_hours_and_days_off_are_refused(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload([
            'starts_at' => '2026-11-09 07:00:00', 'ends_at' => '2026-11-09 09:00:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        app(CurrentGym::class)->set($this->gym);
        WorkSchedule::where('weekday', 'mon')->update(['start_time' => null, 'end_time' => null]);
        $this->postJson($this->url('sessions'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public function test_approved_leave_is_refused(): void
    {
        app(CurrentGym::class)->set($this->gym);
        LeaveRequest::factory()->create(['user_id' => $this->coach->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'status' => LeaveRequestStatus::Approved]);
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public function test_gym_and_national_holidays_are_refused(): void
    {
        app(CurrentGym::class)->set($this->gym);
        GymHoliday::create(['date' => '2026-11-09', 'name' => 'Gym anniversary']);
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload())->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at')->assertSee('Gym anniversary');
        $this->postJson($this->url('sessions'), $this->payload(['starts_at' => '2027-01-01 10:00:00', 'ends_at' => '2027-01-01 11:00:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at')->assertSee('holiday');
    }

    public function test_retired_rooms_and_non_coaches_are_refused_and_options_are_scoped(): void
    {
        $staff = $this->employeeWith(['hr' => 'view']);
        app(CurrentGym::class)->set($this->gym);
        $this->room->update(['is_active' => false]);
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload(['coach_id' => $staff->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['room_id', 'coach_id']);
        $this->getJson($this->url('sessions/options'))->assertOk()->assertJsonCount(0, 'options.rooms')->assertJsonCount(1, 'options.coaches');
    }

    public function test_discipline_must_be_a_platform_sport_or_empty(): void // R-124
    {
        $this->actingAs($this->owner);
        $this->postJson($this->url('sessions'), $this->payload(['discipline' => 'Boxing class']))
            ->assertUnprocessable()->assertJsonValidationErrors('discipline');
        $this->postJson($this->url('sessions'), $this->payload(['discipline' => 'judo']))->assertCreated();
        $this->postJson($this->url('sessions'), $this->payload([
            'discipline' => null, 'starts_at' => '2026-11-09 12:00:00', 'ends_at' => '2026-11-09 13:00:00',
        ]))->assertCreated();
    }

    public function test_options_list_gym_sports_first_and_all_of_them(): void // R-124
    {
        $this->gym->forceFill(['disciplines' => ['bjj']])->save();
        $list = $this->actingAs($this->owner)->getJson($this->url('sessions/options'))->assertOk()
            ->assertJsonPath('options.disciplines.0.value', 'bjj')->json('options.disciplines');
        $this->assertCount(count(config('verification.disciplines')), $list);
    }

    public function test_old_typed_discipline_can_be_kept_but_not_changed_to_new_text(): void // R-124
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload())->assertCreated()->json('session.id');
        TrainingSession::withoutGlobalScopes()->whereKey($id)->update(['discipline' => 'Old text']);
        $this->putJson($this->url("sessions/{$id}"), $this->payload(['discipline' => 'Old text']))->assertOk();
        $this->putJson($this->url("sessions/{$id}"), $this->payload(['discipline' => 'New text']))
            ->assertUnprocessable()->assertJsonValidationErrors('discipline');
    }

    public function test_repeating_sessions_share_series_and_edit_one_occurrence_only(): void
    {
        $response = $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload([
            'repeat' => ['weekdays' => ['mon', 'wed'], 'until' => '2026-11-16'],
        ]))->assertCreated()->assertJsonCount(3, 'sessions');
        $id = $response->json('session.id');
        $series = $response->json('session.series_id');
        $this->assertNotNull($series);
        $this->assertDatabaseCount('training_sessions', 3);
        $this->assertSame(3, TrainingSession::withoutGlobalScope('gym')->where('series_id', $series)->count());
        $this->putJson($this->url("sessions/{$id}"), $this->payload(['name' => 'Changed']))->assertOk();
        $this->assertSame(2, TrainingSession::withoutGlobalScope('gym')->where('name', 'Boxing Basics')->count());
    }

    public function test_repeat_is_atomic_and_reports_failing_dates(): void
    {
        $this->createSession(['starts_at' => '2026-11-11 10:00:00', 'ends_at' => '2026-11-11 11:00:00']);
        $this->postJson($this->url('sessions'), $this->payload(['repeat' => ['weekdays' => ['mon', 'wed'], 'until' => '2026-11-16']]))
            ->assertUnprocessable()->assertSee('2026-11-11');
        $this->assertDatabaseCount('training_sessions', 1);
        $this->postJson($this->url('sessions'), $this->payload(['repeat' => ['weekdays' => ['mon'], 'until' => '2027-06-01']]))
            ->assertUnprocessable()->assertJsonValidationErrors('repeat.until');
        $this->postJson($this->url('sessions'), $this->payload(['repeat' => ['weekdays' => array_keys(config('hr.weekdays')), 'until' => '2027-04-01']]))
            ->assertUnprocessable()->assertJsonValidationErrors('repeat');
    }

    public function test_update_excludes_itself_but_rechecks_other_clashes_and_cancelled_status(): void
    {
        $id = $this->createSession();
        $this->putJson($this->url("sessions/{$id}"), $this->payload())->assertOk();
        $other = $this->createSession(['starts_at' => '2026-11-09 11:00:00', 'ends_at' => '2026-11-09 12:00:00']);
        $this->putJson($this->url("sessions/{$other}"), $this->payload())->assertUnprocessable()->assertJsonValidationErrors(['coach_id', 'room_id']);
        TrainingSession::withoutGlobalScope('gym')->findOrFail($id)->update(['status' => 'cancelled']);
        $this->putJson($this->url("sessions/{$id}"), $this->payload())->assertStatus(409);
    }

    public function test_other_gym_sessions_and_references_are_hidden(): void
    {
        $id = $this->createSession();
        $other = Gym::factory()->create();
        $other->addPerson($this->owner, GymPersonType::Owner);
        $this->app->forgetScopedInstances();
        $this->getJson("/api/gyms/{$other->id}/sessions/{$id}")->assertNotFound();
        $this->putJson("/api/gyms/{$other->id}/sessions/{$id}", $this->payload())->assertNotFound();
        $this->postJson("/api/gyms/{$other->id}/sessions", $this->payload())->assertUnprocessable()->assertJsonValidationErrors(['room_id', 'coach_id', 'allowed_plan_ids.0']);
    }

    public function test_booking_settings_round_trip_and_limits(): void
    {
        $this->actingAs($this->owner)->getJson($this->url('setup'))->assertOk()
            ->assertJsonPath('setup.booking_approval', false)->assertJsonPath('setup.late_grace_minutes', 10)->assertJsonPath('setup.cancel_cutoff_hours', 2)
            ->assertJsonPath('setup.session_reminder_minutes', 60);
        $settings = ['booking_approval' => true, 'late_grace_minutes' => 20, 'cancel_cutoff_hours' => 12, 'session_reminder_minutes' => 120];
        $this->putJson($this->url('setup'), $settings)->assertOk()->assertJsonPath('setup.booking_approval', true);
        $this->getJson($this->url('setup'))->assertOk()->assertJsonPath('setup.late_grace_minutes', 20)->assertJsonPath('setup.cancel_cutoff_hours', 12)
            ->assertJsonPath('setup.session_reminder_minutes', 120);
        $this->putJson($this->url('setup'), ['late_grace_minutes' => 121, 'cancel_cutoff_hours' => 73])->assertUnprocessable()
            ->assertJsonValidationErrors(['late_grace_minutes', 'cancel_cutoff_hours']);
    }

    public function test_session_reminder_setting_accepts_only_supported_choices_and_is_per_gym(): void
    {
        $other = Gym::factory()->create();
        foreach ([30, 60, 120, 1440] as $minutes) {
            $this->actingAs($this->owner)->putJson($this->url('setup'), ['session_reminder_minutes' => $minutes])
                ->assertOk()->assertJsonPath('setup.session_reminder_minutes', $minutes);
            $this->getJson($this->url('setup'))->assertOk()->assertJsonPath('setup.session_reminder_minutes', $minutes);
        }
        foreach ([0, 45, 1441, null, 'invalid', 30.5] as $minutes) {
            $this->putJson($this->url('setup'), ['session_reminder_minutes' => $minutes])
                ->assertUnprocessable()->assertJsonValidationErrors('session_reminder_minutes');
        }
        $this->assertSame(60, $other->fresh()->session_reminder_minutes);
        $this->assertSame(1440, $this->gym->fresh()->session_reminder_minutes);
    }

    public function test_empty_repeat_is_rejected_without_saving(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload(['repeat' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors(['repeat.weekdays', 'repeat.until']);
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_cancelled_occurrences_do_not_block_new_sessions(): void
    {
        $id = $this->createSession();
        TrainingSession::withoutGlobalScope('gym')->findOrFail($id)->update(['status' => 'cancelled']);
        $this->createSession();
        $this->assertDatabaseCount('training_sessions', 2);
    }

    public function test_list_filters_and_show_return_only_matching_occurrences(): void
    {
        $id = $this->createSession();
        $this->getJson($this->url("sessions/{$id}"))->assertOk()->assertJsonPath('session.id', $id);
        $this->getJson($this->url('sessions?from=2026-11-09&to=2026-11-09&coach_id='.$this->coach->id.'&room_id='.$this->room->id))
            ->assertOk()->assertJsonCount(1, 'sessions');
        $this->getJson($this->url('sessions?from=2026-11-10&to=2026-11-15'))->assertOk()->assertJsonCount(0, 'sessions');
        $this->getJson($this->url('sessions?from=2026-11-09&to=2026-11-15&coach_id='.$this->owner->id))
            ->assertOk()->assertJsonCount(0, 'sessions');
        $this->getJson($this->url('sessions?from=2026-11-15&to=2026-11-09'))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_detail_limits_and_cross_gym_service_are_validated(): void
    {
        $service = GymService::factory()->forGym(Gym::factory()->create())->create();
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $this->payload([
            'gym_service_id' => $service->id, 'type' => 'invalid', 'level' => 'invalid', 'capacity' => 501,
            'fee' => -1, 'needs_approval' => 'invalid',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['gym_service_id', 'type', 'level', 'capacity', 'fee', 'needs_approval']);
        $this->postJson($this->url('sessions'), $this->payload(['capacity' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }

    public function test_private_coaching_defaults_to_one_place_and_accepts_included_fee(): void
    {
        $payload = $this->payload(['type' => 'private_coaching', 'fee' => null, 'allowed_plan_ids' => []]);
        unset($payload['capacity']);
        $this->actingAs($this->owner)->postJson($this->url('sessions'), $payload)->assertCreated()
            ->assertJsonPath('session.capacity', 1)->assertJsonPath('session.fee', null)->assertJsonPath('session.allowed_plan_ids', []);
    }

    public function test_phase_follows_philippine_clock_and_cancellation(): void
    {
        $id = $this->createSession();
        $phase = function (string $now) use ($id) {
            $this->travelTo(Carbon::parse($now, 'Asia/Manila'));

            return $this->getJson($this->url("sessions/{$id}"))->assertOk()->json('session.phase');
        };
        $this->assertSame('scheduled', $phase('2026-11-09 09:59:59'));
        $this->assertSame('ongoing', $phase('2026-11-09 10:00:00'));
        $this->assertSame('ongoing', $phase('2026-11-09 10:59:59'));
        $this->assertSame('ended', $phase('2026-11-09 11:00:00'));
        // 02:30 UTC is 10:30 in Manila, so the session is running.
        $this->assertSame('ongoing', $phase('2026-11-09T02:30:00Z'));
        TrainingSession::withoutGlobalScope('gym')->findOrFail($id)->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $phase('2026-11-09 10:30:00'));
        $this->getJson($this->url('sessions?from=2026-11-09&to=2026-11-09'))->assertJsonPath('sessions.0.phase', 'cancelled');
    }

    private function thumb(int $id, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)
            ->post($this->url("sessions/{$id}/thumbnail"), ['photo' => UploadedFile::fake()->createWithContent('a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=')), ...$extra], ['Accept' => 'application/json']);
    }

    private function path(int $id): ?string
    {
        return TrainingSession::withoutGlobalScope('gym')->findOrFail($id)->thumbnail_path;
    }

    // R-125
    public function test_thumbnail_upload_and_replace_delete_old_file(): void
    {
        Storage::fake('public');
        $id = $this->createSession();
        $this->thumb($id)->assertOk()->assertJsonPath('session.thumbnail_url', fn ($url) => $url !== null);
        $first = $this->path($id);
        Storage::disk('public')->assertExists($first);
        $this->thumb($id)->assertOk();
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($this->path($id));
        $this->actingAs($this->owner)->getJson($this->url("timetable/{$id}"))->assertJsonPath('session.thumbnail_url', fn ($url) => $url !== null);
        $this->actingAs($this->owner)->getJson($this->url('sessions/options'))->assertJsonPath('options.thumbnail_max_kb', config('offer.service_photos.max_kb'));
    }

    public function test_thumbnail_apply_to_series_skips_past_and_cancelled(): void
    {
        Storage::fake('public');
        app(CurrentGym::class)->set($this->gym);
        $make = fn ($when, array $extra = []) => TrainingSession::create([
            'type' => 'classes', 'name' => 'X', 'level' => 'all', 'coach_id' => $this->coach->id, 'room_id' => $this->room->id,
            'starts_at' => $when, 'ends_at' => $when->copy()->addHour(), 'capacity' => 5, 'series_id' => 'series-1', ...$extra,
        ])->id;
        $first = $make(now()->addDays(1)->setTime(10, 0));
        $future = $make(now()->addDays(2)->setTime(10, 0));
        $past = $make(now()->subDays(2)->setTime(10, 0));
        $cancelled = $make(now()->addDays(3)->setTime(10, 0), ['status' => 'cancelled']);
        $this->thumb($first, ['apply_to_series' => true])->assertOk();
        $this->assertNotNull($this->path($first));
        $this->assertSame($this->path($first), $this->path($future));
        $this->assertNull($this->path($past));
        $this->assertNull($this->path($cancelled));
        $file = $this->path($first);
        $this->actingAs($this->owner)->deleteJson($this->url("sessions/{$future}/thumbnail"))->assertOk()->assertJsonPath('session.thumbnail_url', null);
        Storage::disk('public')->assertExists($file);
        $this->actingAs($this->owner)->deleteJson($this->url("sessions/{$first}/thumbnail"))->assertOk();
        Storage::disk('public')->assertMissing($file);
    }

    public function test_thumbnail_rules(): void
    {
        Storage::fake('public');
        $id = $this->createSession();
        $this->actingAs($this->owner)->post($this->url("sessions/{$id}/thumbnail"), ['photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->thumb($id, [], $this->coach)->assertForbidden();
        TrainingSession::withoutGlobalScope('gym')->findOrFail($id)->update(['status' => 'cancelled']);
        $this->thumb($id)->assertStatus(409);
    }
}
