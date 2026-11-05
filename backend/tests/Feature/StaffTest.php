<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Notifications\EmployeeAdded;
use App\Notifications\EmployeeInvitation;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-22 and R-23: staff list, adding, removing, pay visibility and coach profiles. */
class StaffTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Concerns\GivesGymRoles;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function role(array $permissions, string $name): GymRole
    {
        return $this->makeRole($permissions, $name);
    }

    private function employee(string $firstName = 'Ella', string $lastName = 'Santos'): User
    {
        $user = User::factory()->create(['first_name' => $firstName, 'last_name' => $lastName]);
        $this->gym->addPerson($user, GymPersonType::Employee);

        return $user;
    }

    public function test_owner_adds_a_new_employee_with_roles_and_sends_an_invite(): void
    {
        Notification::fake();
        $role = $this->role(['training' => 'manage'], 'Coach');

        $this->actingAs($this->owner)->postJson($this->url('staff'), [
            'email' => 'newcoach@example.com',
            'first_name' => 'Nico',
            'last_name' => 'Cruz',
            'role_ids' => [$role->id],
            'job_title' => 'Coach',
            'started_on' => '2026-11-02',
            'pay_basis' => 'monthly',
            'monthly_rate' => 25000,
            'coach_rank' => 'Brown Belt',
        ])
            ->assertCreated()
            ->assertJsonPath('employee.email', 'newcoach@example.com')
            ->assertJsonPath('employee.roles.0', 'Coach')
            ->assertJsonPath('employee.is_coach', true)
            ->assertJsonPath('employee.profile.job_title', 'Coach')
            ->assertJsonPath('employee.profile.started_on', '2026-11-02')
            ->assertJsonPath('employee.profile.monthly_rate', '25000.00');

        $employee = User::where('email', 'newcoach@example.com')->firstOrFail();

        $this->assertTrue($employee->hasGymRole($this->gym, GymPersonType::Employee));
        $this->assertTrue($employee->password_change_required);
        $this->assertNull($employee->email_verified_at);
        $this->assertSame('Coach', $employee->gymRoles($this->gym)->value('name'));
        $this->assertDatabaseHas('employee_profiles', [
            'gym_id' => $this->gym->id,
            'user_id' => $employee->id,
            'job_title' => 'Coach',
        ]);

        Notification::assertSentTo($employee, EmployeeInvitation::class);
        Notification::assertNotSentTo($employee, EmployeeAdded::class);
    }

    public function test_an_existing_account_is_added_and_notified(): void
    {
        Notification::fake();
        $existing = User::factory()->create([
            'first_name' => 'Mara',
            'last_name' => 'Lim',
            'email' => 'mara@example.com',
        ]);
        $role = $this->role(['hr' => 'view'], 'HR Viewer');

        $this->actingAs($this->owner)->postJson($this->url('staff'), [
            'email' => 'mara@example.com',
            'role_ids' => [$role->id],
        ])
            ->assertCreated()
            ->assertJsonPath('employee.id', $existing->id)
            ->assertJsonPath('employee.first_name', 'Mara')
            ->assertJsonPath('employee.roles.0', 'HR Viewer');

        $this->assertTrue($existing->hasGymRole($this->gym, GymPersonType::Employee));
        Notification::assertSentTo($existing, EmployeeAdded::class);
        Notification::assertNotSentTo($existing, EmployeeInvitation::class);
    }

    public function test_hr_cannot_add_someone_with_a_role_above_their_own_access(): void
    {
        Notification::fake();

        $hr = $this->employee('Hana', 'Reyes');
        $this->role(['hr' => 'manage'], 'HR Manager')->users()->attach($hr->id);

        $big = $this->role(['finance' => 'approve'], 'Finance Head');
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($hr)->postJson($this->url('staff'), [
            'email' => 'target@example.com',
            'role_ids' => [$big->id],
        ])->assertForbidden();

        // The failed add is rolled back completely.
        $this->assertFalse($target->hasGymRole($this->gym, GymPersonType::Employee));
        $this->assertDatabaseMissing('employee_profiles', [
            'gym_id' => $this->gym->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_removing_an_employee_ends_access_but_keeps_the_record(): void
    {
        $employee = $this->employee();
        $role = $this->role(['hr' => 'view'], 'HR Viewer');
        $role->users()->attach($employee->id);
        EmployeeProfile::factory()->forGym($this->gym)->create([
            'user_id' => $employee->id,
            'job_title' => 'Front Desk',
        ]);

        $this->actingAs($this->owner)->deleteJson($this->url("staff/{$employee->id}"))
            ->assertOk()
            ->assertJsonPath('message', 'Employee removed.');

        $this->assertFalse($employee->hasGymRole($this->gym, GymPersonType::Employee));
        $this->assertSame(0, DB::table('gym_role_user')->where('user_id', $employee->id)->count());
        $this->assertDatabaseHas('employee_profiles', [
            'gym_id' => $this->gym->id,
            'user_id' => $employee->id,
            'job_title' => 'Front Desk',
        ]);

        $this->actingAs($employee)->getJson($this->url('my-work'))->assertNotFound();
    }

    public function test_pay_is_hidden_from_a_plain_hr_viewer_until_payroll_access(): void
    {
        $employee = $this->employee();
        EmployeeProfile::factory()->forGym($this->gym)->create([
            'user_id' => $employee->id,
            'pay_basis' => 'monthly',
            'monthly_rate' => 25000,
            'per_session_rate' => 500,
        ]);

        $hr = $this->employee('Vera', 'Cruz');
        $this->role(['hr' => 'view'], 'HR Viewer')->users()->attach($hr->id);

        $this->actingAs($hr)->getJson($this->url("staff/{$employee->id}"))
            ->assertOk()
            ->assertJsonMissingPath('employee.profile.monthly_rate')
            ->assertJsonMissingPath('employee.profile.per_session_rate');

        $this->role(['payroll' => 'view'], 'Payroll Viewer')->users()->attach($hr->id);

        $this->actingAs($hr)->getJson($this->url("staff/{$employee->id}"))
            ->assertOk()
            ->assertJsonPath('employee.profile.monthly_rate', '25000.00')
            ->assertJsonPath('employee.profile.per_session_rate', '500.00');
    }

    public function test_an_employee_can_edit_only_their_coach_profile_and_photo(): void
    {
        Storage::fake('public');

        $employee = $this->employee();
        EmployeeProfile::factory()->forGym($this->gym)->create([
            'user_id' => $employee->id,
            'job_title' => 'Front Desk',
            'monthly_rate' => 20000,
        ]);

        $this->actingAs($employee)->putJson($this->url("staff/{$employee->id}/profile"), [
            'coach_rank' => 'Black Belt',
            'coach_specialisation' => 'Boxing',
            'coach_public' => true,
            'job_title' => 'Owner of Everything',
            'monthly_rate' => 99999,
            'photo' => UploadedFile::fake()->createWithContent(
                'coach.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
            ),
        ])
            ->assertOk()
            ->assertJsonPath('employee.profile.coach_rank', 'Black Belt')
            ->assertJsonPath('employee.profile.coach_public', true)
            ->assertJsonPath('employee.profile.job_title', 'Front Desk')
            ->assertJsonMissingPath('employee.profile.monthly_rate');

        $profile = $employee->employeeProfiles()->firstOrFail();
        $this->assertSame('Front Desk', $profile->job_title);
        $this->assertSame('20000.00', $profile->monthly_rate);
        $this->assertStringStartsWith("coach-photos/{$this->gym->id}/", $profile->coach_photo_path);
        Storage::disk('public')->assertExists($profile->coach_photo_path);
    }

    public function test_my_work_returns_the_employee_view_and_owners_get_404(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->getJson($this->url('my-work'))
            ->assertOk()
            ->assertJsonStructure([
                'work' => ['profile', 'schedule', 'balances', 'requests', 'approved_leave_dates', 'holidays'],
            ]);

        $this->actingAs($this->owner)->getJson($this->url('my-work'))->assertNotFound();
    }
}
