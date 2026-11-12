<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-118: coach and athlete share the same goals; athletes touch only their own. */
class TrainingGoalTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
        $this->active();
        $this->coach = $this->employeeWith(['training' => 'manage']);
    }

    public function test_coach_sets_a_goal_and_it_is_achieved_when_the_last_milestone_is_done(): void
    {
        $goals = $this->actingAs($this->coach)->postJson($this->url("athletes/{$this->applicant->id}/goals"), [
            'title' => 'Blue belt by March', 'target_on' => '2027-03-01',
            'milestones' => [['title' => 'Guard passes'], ['title' => 'Sweeps']],
        ])->assertCreated()->json('goals');
        [$first, $second] = $goals[0]['milestones'];

        // The athlete sees the same goal and ticks milestones themselves.
        $this->actingAs($this->applicant)->getJson($this->url("athletes/{$this->applicant->id}/goals"))->assertJsonPath('goals.0.title', 'Blue belt by March');
        $this->actingAs($this->applicant)->putJson($this->url("goal-milestones/{$first['id']}"), ['done' => true])
            ->assertJsonPath('goals.0.done_count', 1)->assertJsonPath('goals.0.status', 'active');
        $this->actingAs($this->applicant)->putJson($this->url("goal-milestones/{$second['id']}"), ['done' => true])
            ->assertJsonPath('goals.0.status', 'achieved');
        $this->actingAs($this->coach)->putJson($this->url("goal-milestones/{$second['id']}"), ['done' => false])
            ->assertJsonPath('goals.0.status', 'active');
    }

    public function test_athlete_manages_own_goals_but_not_anothers(): void
    {
        $id = $this->actingAs($this->applicant)->postJson($this->url("athletes/{$this->applicant->id}/goals"), ['title' => 'Run 5 km'])
            ->assertCreated()->json('goals.0.id');
        $this->actingAs($this->applicant)->putJson($this->url("goals/{$id}"), ['title' => 'Run 10 km', 'status' => 'achieved'])
            ->assertJsonPath('goals.0.title', 'Run 10 km')->assertJsonPath('goals.0.status', 'achieved');

        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Member);
        $this->actingAs($other)->putJson($this->url("goals/{$id}"), ['title' => 'Hacked'])->assertForbidden();
        $this->actingAs($other)->getJson($this->url("athletes/{$this->applicant->id}/goals"))->assertForbidden();
        $this->actingAs($other)->postJson($this->url("athletes/{$this->applicant->id}/goals"), ['title' => 'x'])->assertForbidden();
    }

    public function test_goals_stay_inside_their_gym(): void
    {
        $id = $this->actingAs($this->coach)->postJson($this->url("athletes/{$this->applicant->id}/goals"), ['title' => 'Comp ready'])->json('goals.0.id');

        $otherGym = Gym::factory()->approved()->create();
        $otherGym->addPerson($this->applicant, GymPersonType::Member);
        $this->actingAs($this->applicant)->putJson("/api/gyms/{$otherGym->id}/goals/{$id}", ['title' => 'Moved'])->assertNotFound();
        $this->actingAs($this->applicant)->getJson("/api/gyms/{$otherGym->id}/athletes/{$this->applicant->id}/goals")->assertJsonCount(0, 'goals');
    }
}
