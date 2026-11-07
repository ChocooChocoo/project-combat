<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportAthleteTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_distinct_effectiveness_and_stored_results_reconcile_with_unassigned_entries(): void
    {
        $users = User::factory()->count(4)->create();
        $coach = User::factory()->create();
        foreach (['2026-11-02 10:00:00', '2026-11-03 10:00:00'] as $start) {
            $session = $this->occurrence($start, ['coach_id' => $coach->id, 'status' => 'cancelled']);
            foreach ($users->take(3) as $user) $this->markedBooking($session, 'present', 'cancelled', $user);
        }
        $this->progress($users[0], 'promotion', ['title' => 'Blue belt']);
        $this->progress($users[0], 'assessment');
        $this->progress($users[1], 'competition', ['title' => 'Open Cup', 'division' => 'Lightweight', 'result' => 'Silver, by judges']);
        $this->progress($users[3], 'achievement');
        $this->progress($users[0], 'promotion', ['occurred_on' => '2026-11-04']);
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('athletes').'?from=2026-11-02&to=2026-11-03')->assertOk()
            ->assertJsonPath('attribution', 'coach_based_on_attendance')->assertJsonPath('summary.attending_athletes', 3)
            ->assertJsonPath('summary.athletes_with_progress', 2)->assertJsonPath('summary.effectiveness_rate', 66.67)
            ->assertJsonPath('summary.promotion', 1)->assertJsonPath('summary.assessment', 1)
            ->assertJsonPath('summary.competition', 1)->assertJsonPath('summary.achievement', 1);
        $coaches = collect($response->json('coaches'));
        $this->assertSame(1, $coaches->firstWhere('coach_id', null)['achievement']);
        $this->assertSame(3, $coaches->firstWhere('coach_id', $coach->id)['attending_athletes']);
        $this->assertNotContains($this->owner->id, $coaches->pluck('coach_id')->all());
        $this->assertSame('Silver, by judges', collect($response->json('results'))->firstWhere('type', 'competition')['result']);
        $this->assertSame('Blue belt', collect($response->json('results'))->firstWhere('type', 'promotion')['title']);
        $this->assertStringNotContainsString('PRIVATE NOTE', $response->getContent());
        $this->assertStringContainsString('not a causal', implode(' ', $response->json('meta.notes')));
    }

    public function test_primary_coach_ties_use_count_then_latest_start_then_lowest_id(): void
    {
        $low = User::factory()->create();
        $high = User::factory()->create();
        $athletes = User::factory()->count(3)->create();
        foreach ([[$low, '2026-11-01 10:00:00', [0, 1, 2]], [$high, '2026-11-02 10:00:00', [0, 1]],
            [$low, '2026-11-03 10:00:00', [0]], [$high, '2026-11-01 10:00:00', [2]]] as [$coach, $start, $indexes]) {
            $session = $this->occurrence($start, ['coach_id' => $coach->id]);
            foreach ($indexes as $index) $this->markedBooking($session, 'late', 'booked', $athletes[$index]);
        }
        foreach ($athletes as $index => $user) $this->progress($user, 'promotion', ['title' => 'Athlete '.$index]);
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('athletes').'?from=2026-11-01&to=2026-11-03')->assertOk();
        $rows = collect($response->json('results'))->keyBy('title');
        $this->assertSame($low->id, $rows['Athlete 0']['coach_id']);
        $this->assertSame($high->id, $rows['Athlete 1']['coach_id']);
        $this->assertSame($low->id, $rows['Athlete 2']['coach_id']);
        $this->assertSame(3, collect($response->json('coaches'))->sum('promotion'));
    }

    public function test_empty_and_date_only_inclusive_end_have_exact_types(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('athletes').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.effectiveness_rate', null);
        $this->progress($this->member);
        $this->progress($this->member, 'achievement', ['occurred_on' => '2026-11-03']);
        $this->actingAs($this->owner)->getJson($this->decisionUrl('athletes').'?from=2026-11-02&to=2026-11-02')
            ->assertOk()->assertJsonPath('summary.promotion', 1)->assertJsonPath('summary.achievement', 0)->assertJsonPath('coaches.0.coach_id', null);
    }
}
