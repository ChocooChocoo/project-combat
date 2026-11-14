<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** R-117: coach applications, blocked by overlapping shifts at another gym. */
class CoachApplicationTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private Gym $gymA;

    private Gym $gymB;

    private User $ownerB;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gymA = Gym::factory()->approved()->create(['name' => 'Gym A']);
        $this->gymB = Gym::factory()->approved()->create(['name' => 'Gym B']);
        $this->ownerB = User::factory()->owner()->create();
        $this->gymB->addPerson($this->ownerB, GymPersonType::Owner);
        SystemRoles::seed($this->gymB);

        // Already coaching at gym A on Mondays 08:00-12:00.
        $this->coach = User::factory()->create(['first_name' => 'Carlo', 'last_name' => 'Diaz']);
        $this->gymA->addPerson($this->coach, GymPersonType::Employee);
        EmployeeProfile::factory()->forGym($this->gymA)->create(['user_id' => $this->coach->id, 'coach_rank' => 'Black belt']);
        WorkSchedule::factory()->forGym($this->gymA)->create(['user_id' => $this->coach->id, 'weekday' => 'mon', 'start_time' => '08:00', 'end_time' => '12:00']);
    }

    private function apply(array $availability, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->coach)->postJson("/api/gyms/{$this->gymB->id}/coach-applications", [
            'experience' => 'Five years of boxing coaching.', 'rank' => 'Black belt', 'availability' => $availability,
        ]);
    }

    public function test_overlapping_hours_at_another_gym_block_the_application(): void
    {
        $this->apply([['weekday' => 'mon', 'start' => '11:00', 'end' => '14:00']])
            ->assertJsonValidationErrors('availability')
            ->assertJsonFragment(['Monday 11:00-14:00 overlaps your 08:00-12:00 shift at Gym A.']);
        $this->apply([['weekday' => 'mon', 'start' => '12:00', 'end' => '14:00']])->assertCreated();
    }

    public function test_an_ended_job_does_not_conflict(): void
    {
        EmployeeProfile::withoutGlobalScope('gym')->where('user_id', $this->coach->id)->update(['ended_on' => '2026-01-01']);
        $this->apply([['weekday' => 'mon', 'start' => '09:00', 'end' => '10:00']])->assertCreated();
    }

    public function test_approval_rechecks_and_makes_a_coach(): void
    {
        $id = $this->apply([['weekday' => 'tue', 'start' => '17:00', 'end' => '20:00']])->assertCreated()->json('application.id');
        $this->assertSame(1, $this->ownerB->notifications()->count());

        // A new shift at gym A now overlaps: approval is refused.
        WorkSchedule::factory()->forGym($this->gymA)->create(['user_id' => $this->coach->id, 'weekday' => 'tue', 'start_time' => '18:00', 'end_time' => '21:00']);
        $this->actingAs($this->ownerB)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/{$id}/decision", ['decision' => 'approve'])
            ->assertJsonValidationErrors('availability');

        WorkSchedule::withoutGlobalScope('gym')->where('weekday', 'tue')->delete();
        $this->actingAs($this->ownerB)->getJson("/api/gyms/{$this->gymB->id}/coach-applications")->assertJsonPath('applications.0.conflicts', []);
        $this->actingAs($this->ownerB)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/{$id}/decision", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('application.status', 'approved');

        $this->assertTrue($this->coach->hasGymRole($this->gymB, GymPersonType::Employee));
        app(CurrentGym::class)->set($this->gymB);
        $this->assertTrue(EmployeeProfile::where('user_id', $this->coach->id)->firstOrFail()->isCoach());
        $this->assertSame('17:00', substr((string) WorkSchedule::where('user_id', $this->coach->id)->firstOrFail()->start_time, 0, 5));
        $this->assertTrue(GymRole::where('template_key', 'coach')->firstOrFail()->users()->whereKey($this->coach->id)->exists());
        $this->assertSame('member', $this->coach->fresh()->account_type->value);
        $this->assertSame(1, $this->coach->notifications()->count());
    }

    public function test_approval_uses_a_coach_role_made_by_the_owner_when_there_is_no_starter_role(): void
    {
        // The owner made "Coach" before the starter roles, so SystemRoles skipped the template (no template_key).
        GymRole::withoutGlobalScope('gym')->where('gym_id', $this->gymB->id)->where('template_key', 'coach')->delete();
        $own = GymRole::withoutEvents(fn () => GymRole::withoutGlobalScope('gym')->forceCreate(['gym_id' => $this->gymB->id, 'name' => 'Coach', 'status' => 'active']));

        $id = $this->apply([['weekday' => 'tue', 'start' => '17:00', 'end' => '20:00']])->assertCreated()->json('application.id');
        $this->actingAs($this->ownerB)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/{$id}/decision", ['decision' => 'approve'])->assertOk();

        $this->assertTrue($own->users()->whereKey($this->coach->id)->exists());
    }

    public function test_owner_accounts_staff_and_duplicates_are_refused(): void
    {
        $this->apply([['weekday' => 'wed', 'start' => '09:00', 'end' => '10:00']], $this->ownerB)->assertForbidden();
        $this->apply([['weekday' => 'wed', 'start' => '09:00', 'end' => '10:00']])->assertCreated();
        $this->apply([['weekday' => 'thu', 'start' => '09:00', 'end' => '10:00']])->assertJsonValidationErrors('experience');

        $hr = User::factory()->create();
        $this->gymB->addPerson($hr, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gymB);
        $this->makeRole(['hr.coach_applications.view'], 'HR viewer')->users()->attach($hr->id);
        $this->app->forgetScopedInstances();
        $this->actingAs($hr)->getJson("/api/gyms/{$this->gymB->id}/coach-applications")->assertOk()->assertJsonCount(1, 'applications');
        $this->actingAs($hr)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/1/decision", ['decision' => 'approve'])->assertForbidden();
    }
}
