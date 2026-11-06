<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\PayrollLine;
use App\Models\Room;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\PayslipReleased;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\PayrollSetup;
use Tests\TestCase;

/** R-59 and R-61: every employee gets a line, and the approval chain cannot be skipped. */
class PayrollRunTest extends TestCase
{
    use PayrollSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayrollGym();
    }

    public function test_a_run_period_must_match_the_gym_frequency_and_cannot_overlap(): void
    {
        $this->actingAs($this->hr)->postJson($this->url('payroll/runs'), [
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-15',
        ])->assertUnprocessable();

        $created = $this->actingAs($this->hr)->postJson($this->url('payroll/runs'), [
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-15',
        ])->assertCreated()->assertJsonPath('run.status', 'draft');

        $this->actingAs($this->hr)->postJson($this->url('payroll/runs'), [
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-15',
        ])->assertUnprocessable();

        $runId = $created->json('run.id');
        $this->actingAs($this->hr)->deleteJson($this->url("payroll/runs/{$runId}"))->assertNoContent();
        $this->assertDatabaseMissing('payroll_runs', ['id' => $runId]);
    }

    public function test_settings_are_owner_only_and_rates_are_effective_dated(): void
    {
        $this->actingAs($this->hr)->getJson($this->url('payroll/settings'))
            ->assertOk()
            ->assertJsonPath('settings.frequency', 'semi_monthly')
            ->assertJsonPath('settings.approval_steps', ['owner'])
            ->assertJsonPath('settings.staff_grace_minutes', 10)
            ->assertJsonPath('settings.overtime_min_minutes', 30);

        $this->actingAs($this->hr)->putJson($this->url('payroll/settings'), [
            'frequency' => 'monthly',
        ])->assertForbidden();

        $this->actingAs($this->owner)->putJson($this->url('payroll/settings'), [
            'frequency' => 'monthly',
            'approval_steps' => ['hr_review', 'finance_review', 'owner'],
            'staff_grace_minutes' => 15,
            'overtime_min_minutes' => 45,
        ])->assertOk()
            ->assertJsonPath('settings.frequency', 'monthly')
            ->assertJsonPath('settings.approval_steps', ['hr_review', 'finance_review', 'owner']);

        $this->actingAs($this->owner)->putJson($this->url('payroll/settings'), [
            'approval_steps' => ['owner', 'hr_review'],
        ])->assertUnprocessable();

        $this->actingAs($this->hr)->postJson($this->url('payroll/rates'), [
            'kind' => 'sss',
            'effective_from' => '2026-12-01',
            'source' => 'Gym-specific test rate',
            'brackets' => [
                ['min' => '0.00', 'max' => null, 'employee' => '500.00', 'employer' => '900.00', 'ec' => '10.00'],
            ],
        ])->assertCreated()
            ->assertJsonPath('rate.kind', 'sss')
            ->assertJsonPath('rate.gym_id', $this->gym->id)
            ->assertJsonPath('rate.effective_from', '2026-12-01');

        $this->actingAs($this->hr)->getJson($this->url('payroll/rates'))
            ->assertOk()
            ->assertJsonFragment(['source' => 'Gym-specific test rate']);
    }

    public function test_a_line_for_every_active_and_just_ended_employee(): void
    {
        $this->payrollProfile($this->employee);
        $this->payrollSchedule($this->employee);

        $ended = User::factory()->create(['first_name' => 'Marco', 'last_name' => 'Diaz']);
        $this->gym->addPerson($ended, GymPersonType::Employee);
        $this->payrollProfile($ended, ['ended_on' => '2026-11-10']);
        $this->payrollSchedule($ended);

        $future = User::factory()->create(['first_name' => 'Future', 'last_name' => 'Hire']);
        $this->payrollProfile($future, ['started_on' => '2026-12-01']);

        $past = User::factory()->create(['first_name' => 'Past', 'last_name' => 'Leaver']);
        $this->payrollProfile($past, ['ended_on' => '2026-10-31']);

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $show = $this->actingAs($this->hr)->getJson($this->url("payroll/runs/{$run->id}"))->assertOk();

        $this->assertCount(2, $show->json('lines'));
        $this->assertSame(
            [$this->employee->id, $ended->id],
            collect($show->json('lines'))->pluck('user_id')->sort()->values()->all(),
        );
        $this->assertSame(2, $show->json('run.headcount'));
    }

    public function test_blocking_warnings_stop_submit_until_the_rate_is_fixed(): void
    {
        $this->payrollProfile($this->employee, ['monthly_rate' => null]);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $show = $this->actingAs($this->hr)->getJson($this->url("payroll/runs/{$run->id}"))->assertOk();
        $this->assertTrue(collect($show->json('warnings'))->contains(fn (array $warning) => $warning['code'] === 'missing_monthly_rate' && $warning['blocking'] === true));

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertStatus(409);

        $this->gym->employees()->where('user_id', $this->employee->id)->update(['monthly_rate' => '30000.00']);

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))
            ->assertOk()
            ->assertJsonPath('run.status', 'in_review');
    }

    public function test_compute_is_only_allowed_in_draft(): void
    {
        $this->payrollProfile($this->employee);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertStatus(409);
    }

    public function test_submit_snapshots_the_chain(): void
    {
        $this->gym->update(['payroll_steps' => ['hr_review', 'owner']]);
        $this->payrollProfile($this->employee);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))
            ->assertOk()
            ->assertJsonPath('run.steps', ['hr_review', 'owner'])
            ->assertJsonPath('run.current_step', 0);
    }

    public function test_the_chain_advances_one_step_at_a_time_and_one_person_cannot_act_twice(): void
    {
        $this->gym->update(['payroll_steps' => ['finance_review', 'owner']]);
        $this->payrollProfile($this->employee);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('run.status', 'in_review')
            ->assertJsonPath('run.current_step', 1);

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertForbidden();

        $run->refresh();
        $this->assertSame('in_review', $run->status->value);
        $this->assertSame(1, DB::table('payroll_approvals')->where('payroll_run_id', $run->id)->count());
    }

    public function test_the_preparer_cannot_approve_their_own_run(): void
    {
        $this->gym->update(['payroll_steps' => ['hr_review', 'owner']]);
        $this->payrollProfile($this->employee);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertForbidden();
    }

    public function test_the_owner_can_self_release_the_final_step_and_it_is_flagged(): void
    {
        $this->payrollProfile($this->employee);

        $oneStep = $this->payrollRun(preparedBy: $this->owner);
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$oneStep->id}/compute"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$oneStep->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$oneStep->id}/decision"), [
            'decision' => 'reject',
            'reason' => 'The preparer cannot reject their own run either.',
        ])->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$oneStep->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('run.status', 'released')
            ->assertJsonPath('run.self_released', true);

        $this->gym->update(['payroll_steps' => ['hr_review', 'owner']]);
        $multi = $this->payrollRun('2026-11-16', '2026-11-30', $this->owner);
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$multi->id}/compute"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$multi->id}/submit"))->assertOk();

        // The preparer still cannot act on an earlier step.
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$multi->id}/decision"), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$multi->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        // Once every earlier step is approved, the owner may release the final step.
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$multi->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('run.status', 'released')
            ->assertJsonPath('run.self_released', true);
    }

    public function test_rejection_returns_to_draft_with_a_reason_and_a_new_round(): void
    {
        $this->gym->update(['payroll_steps' => ['hr_review', 'owner']]);
        $this->payrollProfile($this->employee);

        $finance = User::factory()->create(['first_name' => 'Faye', 'last_name' => 'Lim']);
        $this->gym->addPerson($finance, GymPersonType::Employee);
        $this->givePayrollRole($finance, ['payroll.runs.view', 'payroll.runs.approve'], 'Finance approver');

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();

        $this->actingAs($finance)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'reject',
            'reason' => 'One coach is missing a session rate.',
        ])->assertOk()
            ->assertJsonPath('run.status', 'draft')
            ->assertJsonPath('run.round', 2)
            ->assertJsonPath('run.rejection_reason', 'One coach is missing a session rate.');

        $run->refresh();
        $this->assertSame(2, $run->round);
        $this->assertSame(2, DB::table('payroll_approvals')->where('payroll_run_id', $run->id)->count());
        $this->assertSame([1, 1], DB::table('payroll_approvals')->where('payroll_run_id', $run->id)->orderBy('step_index')->pluck('round')->all());

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
    }

    public function test_double_release_posts_each_expense_once(): void
    {
        Notification::fake();
        $this->payrollProfile($this->employee);
        $this->markPresent($this->employee);

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('run.status', 'released');

        $this->assertSame(2, DB::table('expenses')->where('source_type', 'payroll_run')->where('source_id', $run->id)->count());
        $this->assertDatabaseHas('expenses', [
            'source_type' => 'payroll_run', 'source_id' => $run->id, 'category' => 'payroll', 'amount' => '15000.00',
        ]);
        $this->assertDatabaseHas('expenses', [
            'source_type' => 'payroll_run', 'source_id' => $run->id, 'category' => 'statutory_contributions', 'amount' => '775.00',
        ]);
        Notification::assertSentTo($this->employee, PayslipReleased::class);

        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertStatus(409);

        $this->assertSame(2, DB::table('expenses')->where('source_type', 'payroll_run')->where('source_id', $run->id)->count());
    }

    public function test_lines_and_items_are_frozen_after_submit(): void
    {
        $this->payrollProfile($this->employee);
        $run = $this->payrollRun();

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->firstOrFail();
        $before = [
            'id' => $line->id,
            'base_pay' => $line->base_pay,
            'gross_pay' => $line->gross_pay,
            'items' => DB::table('payroll_line_items')->where('payroll_line_id', $line->id)->count(),
        ];

        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertStatus(409);

        $line->refresh();
        $this->assertSame($before, [
            'id' => $line->id,
            'base_pay' => $line->base_pay,
            'gross_pay' => $line->gross_pay,
            'items' => DB::table('payroll_line_items')->where('payroll_line_id', $line->id)->count(),
        ]);
    }

    public function test_a_new_effective_rate_changes_new_runs_but_not_released_history(): void
    {
        $this->payrollProfile($this->employee);

        $first = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$first->id}/compute"))->assertOk();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$first->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$first->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $old = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $first->id)->firstOrFail();

        $this->statutoryRate('sss', '2026-11-16', [
            ['min' => '0.00', 'max' => null, 'employee' => '200.00', 'employer' => '390.00', 'ec' => '20.00'],
        ], 'Later test table');

        $second = $this->payrollRun('2026-11-16', '2026-11-30');
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$second->id}/compute"))->assertOk();

        $new = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $second->id)->firstOrFail();

        $this->assertSame('50.00', $old->refresh()->sss_employee);
        $this->assertSame('100.00', $new->sss_employee);
    }

    public function test_the_session_counting_rule_and_exclusions(): void
    {
        $coach = User::factory()->create(['first_name' => 'Carlo', 'last_name' => 'Cruz']);
        $this->gym->addPerson($coach, GymPersonType::Employee);
        $this->payrollProfile($coach, ['pay_basis' => 'per_session', 'monthly_rate' => null, 'per_session_rate' => '500.00']);

        $member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Torres']);
        $this->gym->addPerson($member, GymPersonType::Member);
        $room = Room::factory()->forGym($this->gym)->create();

        $this->trainingSession($coach, $room, '2026-11-02 10:00:00', attendance: 'present');
        $this->trainingSession($coach, $room, '2026-11-03 10:00:00', attendance: 'late');
        $this->trainingSession($coach, $room, '2026-11-04 10:00:00', attendance: 'absent');
        $this->trainingSession($coach, $room, '2026-11-05 10:00:00', attendance: null);
        $this->trainingSession($coach, $room, '2026-11-06 10:00:00', attendance: 'present', flagged: true);
        $this->trainingSession($coach, $room, '2026-11-09 10:00:00', attendance: 'present', status: 'cancelled');
        $this->trainingSession($coach, $room, '2026-11-20 10:00:00', attendance: 'present');

        $run = $this->payrollRun();
        $this->actingAs($this->hr)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();

        $show = $this->actingAs($this->hr)->getJson($this->url("payroll/runs/{$run->id}"))->assertOk();
        $line = collect($show->json('lines'))->firstWhere('user_id', $coach->id);

        $this->assertSame(2, $line['sessions_count']);
        $this->assertSame('1000.00', $line['session_pay']);
        $this->assertSame(4, collect($line['warnings'])->where('code', 'excluded_session')->count());
    }

    private function trainingSession(
        User $coach,
        Room $room,
        string $endsAt,
        ?string $attendance,
        bool $flagged = false,
        string $status = 'scheduled',
    ): void {
        $end = Carbon::parse($endsAt);
        $session = TrainingSession::create([
            'type' => 'classes',
            'name' => 'Boxing',
            'discipline' => 'boxing',
            'level' => 'all',
            'coach_id' => $coach->id,
            'room_id' => $room->id,
            'starts_at' => $end->copy()->subHour(),
            'ends_at' => $end,
            'capacity' => 8,
            'fee' => '0.00',
            'needs_approval' => false,
            'status' => $status,
            'flagged_at' => $flagged ? now() : null,
            'flag_reason' => $flagged ? 'Coach on leave.' : null,
            'created_by' => $this->owner->id,
        ]);

        if ($attendance !== null) {
            $member = User::factory()->create();
            $this->gym->addPerson($member, GymPersonType::Member);
            SessionBooking::forceCreate([
                'training_session_id' => $session->id,
                'user_id' => $member->id,
                'status' => 'booked',
                'fee' => '0.00',
                'attendance' => $attendance,
            ]);
        }
    }
}
