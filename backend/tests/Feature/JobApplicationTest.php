<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** R-138: job postings and staff applications. */
class JobApplicationTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $member;

    private int $postingId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->gym = Gym::factory()->approved()->create(['name' => 'Iron Gym']);
        $this->owner = User::factory()->owner()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        SystemRoles::seed($this->gym);
        $this->member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Cruz']);

        $this->postingId = $this->makePosting(['title' => 'Front desk clerk'], $this->owner)->assertCreated()->json('posting.id');
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function makePosting(array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->postJson($this->url('job-postings'), [
            'description' => 'Greets members.', 'status' => 'open', ...$extra,
        ]);
    }

    private function apply(?User $user = null, array $extra = [], ?int $posting = null)
    {
        return $this->actingAs($user ?? $this->member)->post(
            "/api/gyms/{$this->gym->id}/job-postings/".($posting ?? $this->postingId).'/applications',
            [
                'experience' => 'Two years at a gym desk.',
                'credentials' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
                'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
                ...$extra,
            ],
            ['Accept' => 'application/json'],
        );
    }

    private function staffWith(array $permissions, string $name): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Employee);
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name)->users()->attach($user->id);
        $this->app->forgetScopedInstances();

        return $user;
    }

    public function test_the_owner_creates_a_posting_but_a_view_only_role_cannot(): void
    {
        $viewer = $this->staffWith(['hr.job_postings.view'], 'Viewer');
        $this->makePosting(['title' => 'Cleaner'], $viewer)->assertForbidden();
        $this->actingAs($viewer)->getJson($this->url('job-postings'))->assertOk()->assertJsonCount(1, 'postings');
        $this->actingAs($this->owner)->putJson($this->url("job-postings/{$this->postingId}"), [
            'title' => 'Front desk clerk', 'description' => 'New text.', 'status' => 'closed',
        ])->assertOk()->assertJsonPath('posting.status', 'closed');
    }

    public function test_a_member_applies_and_the_approver_is_told(): void
    {
        $this->apply()->assertCreated()->assertJsonPath('application.status', 'pending');
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertSame('/gyms/'.$this->gym->id.'/manage/hiring', $this->owner->notifications()->first()->data['url']);
    }

    public function test_a_missing_valid_id_is_refused(): void
    {
        $this->apply(null, ['valid_id' => null])->assertJsonValidationErrors('valid_id');
    }

    public function test_a_closed_posting_cannot_be_applied_to(): void
    {
        JobPosting::withoutGlobalScope('gym')->whereKey($this->postingId)->update(['status' => 'closed']);
        $this->apply()->assertNotFound();
    }

    public function test_staff_owners_and_repeat_applications_are_refused(): void
    {
        $this->apply($this->owner)->assertForbidden();

        $staff = User::factory()->create();
        $this->gym->addPerson($staff, GymPersonType::Employee);
        $this->apply($staff)->assertJsonValidationErrors('experience');

        $this->apply()->assertCreated();
        $this->apply()->assertJsonValidationErrors('experience');
    }

    public function test_approval_adds_the_employee_with_title_and_role(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $role = $this->makeRole(['hr.staff.view'], 'Desk role');
        $this->app->forgetScopedInstances();
        $id = $this->makePosting(['title' => 'Desk lead', 'gym_role_id' => $role->id], $this->owner)->assertCreated()->json('posting.id');
        $application = $this->apply(null, [], $id)->assertCreated()->json('application.id');

        $this->actingAs($this->owner)->postJson($this->url("job-applications/{$application}/decision"), ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('application.status', 'approved');

        $this->assertTrue($this->member->hasGymRole($this->gym, GymPersonType::Employee));
        app(CurrentGym::class)->set($this->gym);
        $this->assertSame('Desk lead', EmployeeProfile::where('user_id', $this->member->id)->firstOrFail()->job_title);
        $this->assertTrue(GymRole::findOrFail($role->id)->users()->whereKey($this->member->id)->exists());
        $this->assertSame('member', $this->member->fresh()->account_type->value);
        $this->assertSame(1, $this->member->notifications()->count());

        $this->actingAs($this->owner)->postJson($this->url("job-applications/{$application}/decision"), ['decision' => 'approve'])->assertStatus(409);
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $application = $this->apply()->assertCreated()->json('application.id');
        $this->actingAs($this->owner)->postJson($this->url("job-applications/{$application}/decision"), ['decision' => 'reject'])
            ->assertJsonValidationErrors('reason');
        $this->actingAs($this->owner)->postJson($this->url("job-applications/{$application}/decision"), ['decision' => 'reject', 'reason' => 'No fit.'])
            ->assertOk()->assertJsonPath('application.status', 'rejected');
        $this->assertFalse($this->member->hasGymRole($this->gym, GymPersonType::Employee));
    }

    public function test_hr_can_view_and_download_but_not_approve(): void
    {
        $application = $this->apply()->assertCreated()->json('application.id');
        $hr = User::factory()->create();
        $this->gym->addPerson($hr, GymPersonType::Employee);
        $template = GymRole::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->where('template_key', 'hr')->firstOrFail();
        $template->users()->attach($hr->id);
        $this->app->forgetScopedInstances();

        $this->actingAs($hr)->getJson($this->url('job-applications'))->assertOk()->assertJsonCount(1, 'applications');
        $this->actingAs($hr)->get($this->url("job-applications/{$application}/files/credentials"))->assertOk();
        $this->actingAs($hr)->postJson($this->url("job-applications/{$application}/decision"), ['decision' => 'approve'])->assertForbidden();

        $this->gym->addPerson($this->member, GymPersonType::Member);
        $this->actingAs($this->member)->get($this->url("job-applications/{$application}/files/valid_id"))->assertForbidden();
        $this->assertSame(1, JobApplication::withoutGlobalScope('gym')->count());
    }

    public function test_the_public_page_lists_only_open_postings(): void
    {
        $this->makePosting(['title' => 'Cleaner', 'status' => 'closed'])->assertCreated();
        $this->getJson("/api/gyms/{$this->gym->id}")->assertOk()
            ->assertJsonCount(1, 'gym.job_postings')->assertJsonPath('gym.job_postings.0.title', 'Front desk clerk');
    }
}
