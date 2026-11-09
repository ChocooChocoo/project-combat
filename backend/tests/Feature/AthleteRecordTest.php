<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-37 to R-41: rank, assessments, competitions, achievements, restricted health, own record. */
class AthleteRecordTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
        $this->approved();
    }

    private function entry(User $by, array $data)
    {
        return $this->actingAs($by)->postJson($this->url("athletes/{$this->applicant->id}/entries"), $data);
    }

    public function test_a_coach_records_promotions_and_the_rank_history_is_in_order(): void
    {
        $coach = $this->employeeWith(['training' => 'manage']);

        $this->entry($coach, ['type' => 'promotion', 'title' => 'Blue belt', 'occurred_on' => '2026-01-10'])
            ->assertJsonValidationErrors(['discipline']);
        $this->entry($coach, ['type' => 'promotion', 'discipline' => 'BJJ', 'title' => 'Blue belt', 'occurred_on' => '2026-01-10'])->assertCreated();
        $this->entry($coach, ['type' => 'promotion', 'discipline' => 'BJJ', 'title' => 'Purple belt', 'occurred_on' => '2026-09-01'])->assertCreated();
        $this->entry($coach, ['type' => 'assessment', 'discipline' => 'BJJ', 'title' => 'Guard passing', 'result' => '4 of 5', 'occurred_on' => '2026-09-02'])->assertCreated();
        $this->entry($coach, ['type' => 'promotion', 'discipline' => 'BJJ', 'title' => 'Brown', 'occurred_on' => today()->addDay()->toDateString()])
            ->assertJsonValidationErrors(['occurred_on']);

        $record = $this->actingAs($coach)->getJson($this->url("athletes/{$this->applicant->id}/record"))->assertOk();
        $record->assertJsonPath('record.ranks.0.rank', 'Purple belt')
            ->assertJsonPath('record.entries.1.title', 'Purple belt')
            ->assertJsonPath('record.entries.2.title', 'Blue belt')
            ->assertJsonPath('record.entries.2.recorded_by', trim("{$coach->first_name} {$coach->last_name}"));
    }

    public function test_athlete_view_alone_reaches_the_member_list_and_record(): void
    {
        $viewer = $this->employeeWith(['training.athletes.view']);

        $this->actingAs($viewer)->getJson($this->url('memberships?view=members'))->assertOk();
        $this->actingAs($viewer)->getJson($this->url("athletes/{$this->applicant->id}/record"))->assertOk();
    }

    public function test_competitions_and_achievements_are_kept_and_viewers_cannot_add(): void
    {
        $coach = $this->employeeWith(['training' => 'manage']);
        $this->entry($coach, ['type' => 'competition', 'title' => 'Cavite Open', 'division' => 'Lightweight', 'occurred_on' => '2026-05-01'])
            ->assertJsonValidationErrors(['result']);
        $this->entry($coach, ['type' => 'competition', 'title' => 'Cavite Open', 'division' => 'Lightweight', 'result' => 'Gold', 'occurred_on' => '2026-05-01'])->assertCreated();
        $this->entry($coach, ['type' => 'achievement', 'title' => '100 classes', 'occurred_on' => '2026-06-01'])->assertCreated();

        $viewer = $this->employeeWith(['training' => 'view']);
        $this->entry($viewer, ['type' => 'achievement', 'title' => 'x', 'occurred_on' => '2026-06-01'])->assertForbidden();

        $this->actingAs($this->applicant)->getJson($this->url('my-record'))
            ->assertOk()
            ->assertJsonPath('record.entries.0.title', '100 classes')
            ->assertJsonPath('record.entries.1.division', 'Lightweight')
            ->assertJsonPath('record.entries.1.result', 'Gold');
    }

    public function test_health_details_need_health_permission_on_every_request(): void
    {
        $coach = $this->employeeWith(['training' => 'manage', 'members' => 'view']);
        $this->actingAs($coach)->getJson($this->url("athletes/{$this->applicant->id}/record"))
            ->assertOk()->assertJsonMissingPath('record.health');
        $this->actingAs($coach)->get($this->url("athletes/{$this->applicant->id}/medical-file"))->assertForbidden();

        $nurse = $this->employeeWith(['training' => 'view', 'health_records' => 'view']);
        $this->actingAs($nurse)->getJson($this->url("athletes/{$this->applicant->id}/record"))
            ->assertJsonPath('record.health.emergency_contacts.0.name', 'Maria Cruz');

        $nobody = $this->employeeWith(['payroll' => 'view']);
        $this->actingAs($nobody)->getJson($this->url("athletes/{$this->applicant->id}/record"))->assertForbidden();
    }

    public function test_the_athlete_updates_their_own_health_and_emergency_contacts(): void
    {
        Storage::fake('local');
        $answers = array_fill_keys(array_keys(config('membership.health_questions')), '0');
        $answers['medication'] = '1';

        $this->actingAs($this->applicant)->post($this->url('my-record/health'), [
            'emergency_contacts' => [['name' => 'Jose Cruz', 'relationship' => 'Father', 'phone' => '+63 917 000 1111']],
            'health_answers' => $answers,
            'health_notes' => 'Asthma inhaler',
            'medical_file' => UploadedFile::fake()->create('clearance.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('record.health.emergency_contacts.0.name', 'Jose Cruz')
            ->assertJsonPath('record.health.answers.medication', true)
            ->assertJsonPath('record.health.medical_file_name', 'clearance.pdf');

        $this->actingAs($this->applicant)->get($this->url("athletes/{$this->applicant->id}/medical-file"))->assertOk();

        $this->actingAs($this->applicant)->putJson($this->url('my-record/health'), ['emergency_contacts' => [], 'health_answers' => $answers])
            ->assertJsonValidationErrors(['emergency_contacts']);
    }

    public function test_an_athlete_never_sees_another_athletes_record(): void
    {
        $other = User::factory()->create();
        $this->apply($other);
        $this->actingAs($this->owner)->postJson($this->url('memberships/'.$this->membershipOf($other).'/decision'), ['decision' => 'approve']);

        $this->actingAs($this->applicant)->getJson($this->url("athletes/{$other->id}/record"))->assertForbidden();
        $this->actingAs($this->applicant)->get($this->url("athletes/{$other->id}/medical-file"))->assertForbidden();
        $this->actingAs($this->applicant)->getJson($this->url('memberships'))->assertForbidden();

        $this->actingAs($other)->getJson($this->url('my-record'))
            ->assertOk()->assertJsonPath('record.athlete.id', $other->id);
    }

    private function membershipOf(User $user): int
    {
        return Membership::withoutGlobalScope('gym')->where('user_id', $user->id)->value('id');
    }
}
