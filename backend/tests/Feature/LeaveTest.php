<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\LeaveRequestStatus;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\GymRole;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** R-25 and R-26: leave requests, decisions, attachments and balances. */
class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $employee;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create(['first_name' => 'Ella', 'last_name' => 'Santos']);
        $this->hr = User::factory()->create(['first_name' => 'Hana', 'last_name' => 'Reyes']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
        $this->gym->addPerson($this->hr, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);

        GymRole::create(['name' => 'HR Manager', 'permissions' => ['hr' => 'manage']])
            ->users()
            ->attach($this->hr->id);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function submit(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->postJson($this->url('leave-requests'), array_merge([
            'type' => 'vacation',
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-06',
            'reason' => 'Family trip.',
        ], $overrides));
    }

    public function test_working_days_exclude_the_weekend_and_a_gym_holiday(): void
    {
        Notification::fake();
        GymHoliday::factory()->forGym($this->gym)->create([
            'date' => '2026-11-04',
            'name' => 'Local fiesta',
        ]);

        $this->submit($this->employee, ['ends_on' => '2026-11-08'])
            ->assertCreated()
            ->assertJsonPath('request.working_days', 4)
            ->assertJsonPath('request.status', 'pending');
    }

    public function test_own_pending_request_can_be_cancelled(): void
    {
        Notification::fake();
        $id = $this->submit($this->employee)->assertCreated()->json('request.id');

        $this->actingAs($this->employee)->postJson($this->url("leave-requests/{$id}/cancel"))
            ->assertOk()
            ->assertJsonPath('request.status', 'cancelled');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $id,
            'status' => LeaveRequestStatus::Cancelled->value,
        ]);
    }

    public function test_hr_cannot_decide_their_own_leave_request(): void
    {
        Notification::fake();
        $id = $this->submit($this->hr)->assertCreated()->json('request.id');

        $this->actingAs($this->hr)->postJson($this->url("leave-requests/{$id}/decision"), [
            'decision' => 'approve',
        ])->assertForbidden();
    }

    public function test_submitting_leave_notifies_every_approver(): void
    {
        Notification::fake();

        $this->submit($this->employee)->assertCreated();

        Notification::assertSentTo($this->owner, LeaveRequestSubmitted::class);
        Notification::assertSentTo($this->hr, LeaveRequestSubmitted::class);
    }

    public function test_hr_decides_a_request_and_notifies_the_employee(): void
    {
        Notification::fake();
        $id = $this->submit($this->employee)->assertCreated()->json('request.id');

        $this->actingAs($this->hr)->postJson($this->url("leave-requests/{$id}/decision"), [
            'decision' => 'approve',
            'note' => 'Approved. Enjoy.',
        ])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.decision_note', 'Approved. Enjoy.')
            ->assertJsonPath('request.decided_by', $this->hr->id);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $id,
            'status' => LeaveRequestStatus::Approved->value,
            'decided_by' => $this->hr->id,
        ]);

        Notification::assertSentTo($this->employee, LeaveRequestDecided::class);
    }

    public function test_an_attachment_is_only_for_the_owner_or_an_approver(): void
    {
        Storage::fake('local');
        Notification::fake();

        $id = $this->submit($this->employee, [
            'attachment' => UploadedFile::fake()->create('medical.pdf', 200, 'application/pdf'),
        ])->assertCreated()->json('request.id');

        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Employee);

        $this->actingAs($other)->getJson($this->url("leave-requests/{$id}/attachment"))->assertForbidden();
        $this->actingAs($this->employee)->getJson($this->url("leave-requests/{$id}/attachment"))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->owner)->getJson($this->url("leave-requests/{$id}/attachment"))->assertOk();
    }

    public function test_balances_compute_used_and_remaining_from_approved_leave(): void
    {
        LeaveBalance::factory()->forGym($this->gym)->create([
            'user_id' => $this->employee->id,
            'type' => 'vacation',
            'year' => 2026,
            'allowance' => 10,
        ]);

        LeaveRequest::factory()->forGym($this->gym)->create([
            'user_id' => $this->employee->id,
            'type' => 'vacation',
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-04',
            'working_days' => 3,
            'status' => LeaveRequestStatus::Approved,
        ]);

        $response = $this->actingAs($this->employee)->getJson($this->url('leave-balances?year=2026'))->assertOk();
        $types = collect($response->json('balances.0.types'))->keyBy('type');

        $this->assertSame(10, $types['vacation']['allowance']);
        $this->assertSame(3, $types['vacation']['used']);
        $this->assertSame(7, $types['vacation']['remaining']);
        $this->assertSame(15, $types['sick']['allowance']);
        $this->assertSame(0, $types['sick']['used']);
    }

    public function test_an_over_balance_request_is_flagged_not_blocked(): void
    {
        Notification::fake();
        LeaveBalance::factory()->forGym($this->gym)->create([
            'user_id' => $this->employee->id,
            'type' => 'vacation',
            'year' => 2026,
            'allowance' => 1,
        ]);

        $id = $this->submit($this->employee)->assertCreated()
            ->assertJsonPath('request.over_balance', true)
            ->json('request.id');

        $this->actingAs($this->employee)->getJson($this->url('leave-requests'))
            ->assertOk()
            ->assertJsonPath('requests.0.over_balance', true);

        $this->actingAs($this->hr)->postJson($this->url("leave-requests/{$id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('request.status', 'approved');
    }

    public function test_hr_sees_every_request_while_an_employee_sees_only_their_own(): void
    {
        Notification::fake();
        $this->submit($this->employee)->assertCreated();
        $this->submit($this->hr, ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-08'])->assertCreated();

        $this->actingAs($this->employee)->getJson($this->url('leave-requests'))
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.user_id', $this->employee->id);

        $this->actingAs($this->hr)->getJson($this->url('leave-requests'))
            ->assertOk()
            ->assertJsonCount(2, 'requests');

        $this->actingAs($this->hr)->getJson($this->url('leave-requests?status=approved'))
            ->assertOk()
            ->assertJsonCount(0, 'requests');
    }

    public function test_another_gyms_leave_request_is_not_found(): void
    {
        $otherGym = Gym::factory()->create();
        $otherEmployee = User::factory()->create();
        $otherGym->addPerson($otherEmployee, GymPersonType::Employee);

        $leave = LeaveRequest::factory()->forGym($otherGym)->create([
            'user_id' => $otherEmployee->id,
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-03',
        ]);

        $this->actingAs($this->owner)->getJson($this->url("leave-requests/{$leave->id}"))->assertNotFound();
        $this->actingAs($this->owner)->getJson($this->url("leave-requests/{$leave->id}/attachment"))->assertNotFound();
    }
}
