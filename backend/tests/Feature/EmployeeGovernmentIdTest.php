<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** R-121: government ID numbers on the employee record. */
class EmployeeGovernmentIdTest extends TestCase
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

    private function employee(array $access = []): User
    {
        $user = User::factory()->create();
        $this->gym->addPerson($user, GymPersonType::Employee);

        if ($access) {
            $this->makeRole($access, 'Role '.$user->id)->users()->attach($user->id);
        }

        return $user;
    }

    private function saveSss(User $target, string $value = '12-3456789-0')
    {
        return $this->actingAs($this->owner)->putJson($this->url("staff/{$target->id}"), ['sss_number' => $value]);
    }

    public function test_hr_saves_digits_only_and_the_database_value_is_encrypted(): void
    {
        $target = $this->employee();

        $this->saveSss($target)->assertOk()->assertJsonPath('employee.profile.sss_number', '1234567890');

        $raw = DB::table('employee_profiles')->where('user_id', $target->id)->value('sss_number');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('1234567890', $raw);
    }

    public function test_profile_endpoint_also_saves_ids(): void
    {
        $target = $this->employee();

        $this->actingAs($this->owner)->putJson($this->url("staff/{$target->id}/profile"), [
            'philhealth_number' => '12 3456 7890 12',
            'pagibig_number' => '123456789012',
        ])->assertOk()
            ->assertJsonPath('employee.profile.philhealth_number', '123456789012')
            ->assertJsonPath('employee.profile.pagibig_number', '123456789012');
    }

    public function test_wrong_length_is_rejected(): void
    {
        $target = $this->employee();

        $this->saveSss($target, '12345')->assertStatus(422)->assertJsonValidationErrors('sss_number');
    }

    public function test_view_only_role_sees_masked_numbers(): void
    {
        $target = $this->employee();
        $this->saveSss($target)->assertOk();
        $viewer = $this->employee(['hr.staff.view']);

        $this->actingAs($viewer)->getJson($this->url("staff/{$target->id}"))
            ->assertOk()->assertJsonPath('employee.profile.sss_number', '****7890');
    }

    public function test_payroll_viewer_sees_full_numbers(): void
    {
        $target = $this->employee();
        $this->saveSss($target)->assertOk();
        $viewer = $this->employee(['hr.staff.view', 'payroll.runs.view']);

        $this->actingAs($viewer)->getJson($this->url("staff/{$target->id}"))
            ->assertOk()->assertJsonPath('employee.profile.sss_number', '1234567890');
    }

    public function test_employee_sees_own_full_number_in_my_work(): void
    {
        $target = $this->employee();
        $this->saveSss($target)->assertOk();

        $this->actingAs($target)->getJson($this->url('my-work'))
            ->assertOk()->assertJsonPath('work.profile.sss_number', '1234567890');
    }

    public function test_employee_cannot_change_own_ids_without_hr_manage(): void
    {
        $target = $this->employee();
        $this->saveSss($target)->assertOk();

        $this->actingAs($target)->putJson($this->url("staff/{$target->id}/profile"), [
            'coach_rank' => 'Blue Belt',
            'sss_number' => '0000000000',
        ])->assertOk()->assertJsonPath('employee.profile.sss_number', '1234567890');

        $this->assertSame('1234567890', \App\Models\EmployeeProfile::withoutGlobalScope('gym')
            ->where('user_id', $target->id)->first()->sss_number);
    }

    public function test_other_gym_gets_404(): void
    {
        $target = $this->employee();
        $otherGym = Gym::factory()->create();
        $otherOwner = User::factory()->create();
        $otherGym->addPerson($otherOwner, GymPersonType::Owner);

        $this->actingAs($otherOwner)->getJson("/api/gyms/{$otherGym->id}/staff/{$target->id}")->assertNotFound();
    }
}
