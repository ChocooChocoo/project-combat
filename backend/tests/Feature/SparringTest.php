<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** (R-140) sparring partner suggestions on the athlete record. */
class SparringTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
        $this->approved(); // the applicant is the target; their membership is not active, which is fine for a target
    }

    /** An active athlete at this gym, with the given sparring details. */
    private function athlete(array $profile, string $first = 'Ben'): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => 'Reyes']);
        $membership = $this->apply($user);
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])->assertOk();
        $this->actingAs($this->owner)
            ->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 1500, 'method' => 'cash'])->assertCreated();
        DB::table('athlete_profiles')->where('gym_id', $this->gym->id)->where('user_id', $user->id)->update($profile);

        return $user;
    }

    private function getMatches(?User $by = null, ?User $target = null)
    {
        return $this->actingAs($by ?? $this->owner)
            ->getJson($this->url('athletes/'.($target ?? $this->applicant)->id.'/sparring-matches'));
    }

    public function test_the_closest_weight_is_listed_first_and_far_weights_are_left_out(): void
    {
        DB::table('athlete_profiles')->where('user_id', $this->applicant->id)->update(['weight_kg' => 60]);
        $near = $this->athlete(['weight_kg' => 62], 'Near');
        $this->athlete(['weight_kg' => 75], 'Far');

        $response = $this->getMatches()->assertOk();
        $response->assertJsonCount(1, 'matches')
            ->assertJsonPath('matches.0.user_id', $near->id)
            ->assertJsonPath('notes', []);
        $this->assertContains('2 kg apart', $response->json('matches.0.reasons'));
    }

    public function test_minors_and_adults_are_not_matched_but_unknown_ages_are(): void
    {
        $this->applicant->forceFill(['birthdate' => today()->subYears(30)])->save();
        DB::table('athlete_profiles')->where('user_id', $this->applicant->id)->update(['weight_kg' => 60, 'experience_years' => 2]);
        $adult = $this->athlete(['weight_kg' => 61, 'experience_years' => 3], 'Adult');
        $adult->forceFill(['birthdate' => today()->subYears(25)])->save();
        $minor = $this->athlete(['weight_kg' => 60], 'Minor');
        $minor->forceFill(['birthdate' => today()->subYears(14)])->save();
        $unknown = $this->athlete(['weight_kg' => 60], 'Unknown');
        $unknown->forceFill(['birthdate' => null])->save();

        $ids = collect($this->getMatches()->assertOk()->json('matches'))->pluck('user_id');
        $this->assertTrue($ids->contains($adult->id));
        $this->assertTrue($ids->contains($unknown->id));
        $this->assertFalse($ids->contains($minor->id));
        $adultRow = collect($this->getMatches()->json('matches'))->firstWhere('user_id', $adult->id);
        $this->assertContains('1 year more experience', $adultRow['reasons']);

        $minorResponse = $this->getMatches(target: $minor)->assertOk();
        $this->assertContains('This athlete is under 18, so only other athletes under 18 are suggested.', $minorResponse->json('notes'));
        $forMinor = collect($minorResponse->json('matches'))->pluck('user_id');
        $this->assertFalse($forMinor->contains($adult->id));
        $this->assertFalse($forMinor->contains($this->applicant->id));
        $this->assertTrue($forMinor->contains($unknown->id));
    }

    public function test_a_candidate_without_weight_is_listed_after_one_with_weight(): void
    {
        DB::table('athlete_profiles')->where('user_id', $this->applicant->id)->update(['weight_kg' => 60, 'level' => 'beginner']);
        $known = $this->athlete(['weight_kg' => 62, 'level' => 'advanced'], 'Known');
        $unknown = $this->athlete(['weight_kg' => null, 'level' => 'beginner'], 'Unknown');

        $matches = $this->getMatches()->assertOk()->json('matches');
        $this->assertSame($known->id, $matches[0]['user_id']);
        $this->assertSame($unknown->id, $matches[1]['user_id']);
        $this->assertContains('Weight not recorded', $matches[1]['reasons']);
    }

    public function test_the_same_level_scores_higher_than_two_levels_apart(): void
    {
        DB::table('athlete_profiles')->where('user_id', $this->applicant->id)->update(['weight_kg' => 60, 'level' => 'beginner']);
        $same = $this->athlete(['weight_kg' => 60, 'level' => 'beginner'], 'Same');
        $far = $this->athlete(['weight_kg' => 60, 'level' => 'advanced'], 'Far');

        $matches = $this->getMatches()->assertOk()->json('matches');
        $this->assertSame($same->id, $matches[0]['user_id']);
        $this->assertSame($far->id, $matches[1]['user_id']);
        $this->assertGreaterThan($matches[1]['score'], $matches[0]['score']);
    }

    public function test_a_frozen_or_expired_member_is_not_listed(): void
    {
        DB::table('athlete_profiles')->where('user_id', $this->applicant->id)->update(['weight_kg' => 60]);
        $frozen = $this->athlete(['weight_kg' => 61], 'Frozen');
        $expired = $this->athlete(['weight_kg' => 62], 'Expired');
        $active = $this->athlete(['weight_kg' => 63], 'Active');
        DB::table('memberships')->where('user_id', $frozen->id)->update(['status' => MembershipStatus::Frozen->value]);
        DB::table('memberships')->where('user_id', $expired->id)->update(['status' => MembershipStatus::Expired->value]);

        $ids = collect($this->getMatches()->assertOk()->json('matches'))->pluck('user_id')->all();
        $this->assertSame([$active->id], $ids);
    }

    public function test_a_missing_target_weight_returns_a_note(): void
    {
        $this->athlete(['weight_kg' => 60]);

        $this->getMatches()->assertOk()
            ->assertJsonPath('notes.0', "Add this athlete's weight for safer matches.")
            ->assertJsonCount(1, 'matches');
    }

    public function test_a_coach_saves_sparring_details_and_ranges_are_checked(): void
    {
        $coach = $this->employeeWith(['training' => 'manage']);
        $url = $this->url("athletes/{$this->applicant->id}/sparring");

        $this->actingAs($coach)->putJson($url, ['weight_kg' => 300])->assertJsonValidationErrors(['weight_kg']);
        $this->actingAs($coach)->putJson($url, ['height_cm' => 50])->assertJsonValidationErrors(['height_cm']);
        $this->actingAs($coach)->putJson($url, ['weight_kg' => 61.5, 'height_cm' => 170, 'wins' => 4, 'losses' => 1, 'draws' => 0, 'experience_years' => 3])
            ->assertOk()->assertJsonPath('sparring.weight_kg', 61.5)->assertJsonPath('sparring.wins', 4);

        $this->actingAs($coach)->getJson($this->url("athletes/{$this->applicant->id}/record"))
            ->assertJsonPath('record.sparring.height_cm', 170)
            ->assertJsonPath('record.sparring.experience_years', 3);
    }

    public function test_a_coach_cannot_change_their_own_record(): void
    {
        $coach = $this->employeeWith(['training' => 'manage']);
        $membership = $this->apply($coach);
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])->assertOk();

        $this->actingAs($coach)->putJson($this->url("athletes/{$coach->id}/sparring"), ['weight_kg' => 70])->assertForbidden();
    }

    public function test_a_role_without_view_permissions_is_refused(): void
    {
        $nobody = $this->employeeWith(['payroll' => 'view']);

        $this->getMatches($nobody)->assertForbidden();
        $this->actingAs($nobody)->putJson($this->url("athletes/{$this->applicant->id}/sparring"), ['wins' => 1])->assertForbidden();
    }

    public function test_decision_support_view_alone_can_see_matches_but_not_edit(): void
    {
        $analyst = $this->employeeWith(['decision_support' => 'view']);

        $this->getMatches($analyst)->assertOk();
        $this->actingAs($analyst)->putJson($this->url("athletes/{$this->applicant->id}/sparring"), ['wins' => 1])->assertForbidden();
    }

    public function test_another_gyms_athlete_is_not_found(): void
    {
        $other = Gym::factory()->approved()->create();
        $stranger = User::factory()->create();
        DB::table('athlete_profiles')->insert([
            'gym_id' => $other->id, 'user_id' => $stranger->id, 'emergency_contacts' => '[]', 'health_answers' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getMatches($this->owner, $stranger)->assertNotFound();
        $this->actingAs($this->owner)->putJson($this->url("athletes/{$stranger->id}/sparring"), ['wins' => 1])->assertNotFound();
    }
}
