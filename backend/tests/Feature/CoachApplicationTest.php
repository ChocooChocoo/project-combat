<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\CoachApplication;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** R-117: coach applications, blocked by overlapping shifts at another gym. (R-146) Documents go with them. */
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
        Storage::fake('local');
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

    private function apply(array $availability, ?User $user = null, array $extra = [])
    {
        return $this->actingAs($user ?? $this->coach)->post("/api/gyms/{$this->gymB->id}/coach-applications", [
            'experience' => 'Five years of boxing coaching.', 'rank' => 'Black belt', 'availability' => $availability,
            // (R-146) Resume, a valid ID and a medical clearance are needed; certifications are optional.
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            'certifications' => UploadedFile::fake()->create('certifications.pdf', 100, 'application/pdf'),
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'medical_clearance' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
            ...$extra,
        ], ['Accept' => 'application/json']);
    }

    private function fileUrl(int $application, string $kind): string
    {
        return "/api/gyms/{$this->gymB->id}/coach-applications/{$application}/files/{$kind}";
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

    public function test_documents_are_required_and_certifications_are_optional(): void
    {
        $availability = [['weekday' => 'wed', 'start' => '09:00', 'end' => '10:00']];

        $this->apply($availability, null, ['resume' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['resume' => 'Attach your resume.']);
        $this->apply($availability, null, ['valid_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['valid_id' => 'Attach a valid ID.']);
        $this->apply($availability, null, ['medical_clearance' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['medical_clearance' => 'Attach your medical clearance.']);

        // Without certifications the application still goes through.
        $this->apply($availability, null, ['certifications' => null])->assertCreated()->assertJsonPath('application.certifications_name', null);
    }

    public function test_the_gym_can_open_each_document(): void
    {
        $application = $this->apply([['weekday' => 'tue', 'start' => '17:00', 'end' => '20:00']])
            ->assertCreated()
            ->assertJsonPath('application.resume_name', 'resume.pdf')
            ->assertJsonPath('application.certifications_name', 'certifications.pdf')
            ->assertJsonPath('application.valid_id_name', 'id.jpg')
            ->assertJsonPath('application.medical_clearance_name', 'clearance.pdf')
            ->json('application.id');

        foreach (['resume', 'certifications', 'valid_id', 'medical_clearance'] as $kind) {
            $this->actingAs($this->ownerB)->get($this->fileUrl($application, $kind))->assertOk();
        }

        // A stored path never leaves the server.
        $row = CoachApplication::withoutGlobalScope('gym')->findOrFail($application);
        $this->assertNotNull($row->resume_path);
        $this->assertArrayNotHasKey('resume_path', $row->toArray());

        $member = User::factory()->create();
        $this->gymB->addPerson($member, GymPersonType::Member);
        $this->actingAs($member)->get($this->fileUrl($application, 'resume'))->assertForbidden();
        $this->actingAs($this->ownerB)->get($this->fileUrl($application, 'diploma'))->assertNotFound();
    }

    public function test_an_application_without_files_still_lists_and_decides(): void
    {
        // Applications sent before this change carry no documents.
        $id = $this->apply([['weekday' => 'tue', 'start' => '17:00', 'end' => '20:00']])->assertCreated()->json('application.id');
        CoachApplication::withoutGlobalScope('gym')->whereKey($id)->update([
            'resume_path' => null, 'resume_name' => null, 'certifications_path' => null, 'certifications_name' => null,
            'valid_id_path' => null, 'valid_id_name' => null, 'medical_clearance_path' => null, 'medical_clearance_name' => null,
        ]);

        $this->actingAs($this->ownerB)->getJson("/api/gyms/{$this->gymB->id}/coach-applications")
            ->assertOk()->assertJsonPath('applications.0.resume_name', null);
        $this->actingAs($this->ownerB)->get($this->fileUrl($id, 'resume'))->assertNotFound();
        $this->actingAs($this->ownerB)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/{$id}/decision", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('application.status', 'approved');
    }

    public function test_hr_can_view_and_download_but_not_approve(): void
    {
        $application = $this->apply([['weekday' => 'tue', 'start' => '17:00', 'end' => '20:00']])->assertCreated()->json('application.id');
        $hr = User::factory()->create();
        $this->gymB->addPerson($hr, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gymB);
        $this->makeRole(['hr.coach_applications.view'], 'HR viewer')->users()->attach($hr->id);
        $this->app->forgetScopedInstances();

        $this->actingAs($hr)->getJson("/api/gyms/{$this->gymB->id}/coach-applications")->assertOk()->assertJsonCount(1, 'applications');
        foreach (['resume', 'certifications', 'valid_id', 'medical_clearance'] as $kind) {
            $this->actingAs($hr)->get($this->fileUrl($application, $kind))->assertOk();
        }
        $this->actingAs($hr)->postJson("/api/gyms/{$this->gymB->id}/coach-applications/{$application}/decision", ['decision' => 'approve'])->assertForbidden();
    }
}
