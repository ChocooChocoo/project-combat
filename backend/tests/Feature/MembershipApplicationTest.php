<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Notifications\MembershipApplicationSubmitted;
use App\Notifications\MembershipUpdated;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-31 to R-33: applying, health and waivers, and the staff decision. */
class MembershipApplicationTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    public function test_a_signed_in_user_applies_and_sees_the_status(): void
    {
        Notification::fake();

        $membership = $this->apply();

        $this->assertSame(MembershipStatus::Applied, $membership->status);
        Notification::assertSentTo($this->owner, MembershipApplicationSubmitted::class);

        $this->actingAs($this->applicant)->getJson('/api/my-memberships')
            ->assertOk()
            ->assertJsonPath('memberships.0.status', 'applied')
            ->assertJsonPath('memberships.0.gym_name', $this->gym->name);
    }

    public function test_plan_emergency_contacts_and_waivers_are_required(): void
    {
        $payload = $this->applicationPayload();
        unset($payload['plan_id']);
        $payload['emergency_contacts'] = [];
        $payload['waivers']['liability'] = false;

        $this->actingAs($this->applicant)->postJson($this->url('membership-applications'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id', 'emergency_contacts', 'waivers.liability']);

        $payload = $this->applicationPayload(['signature_name' => '']);
        $this->actingAs($this->applicant)->postJson($this->url('membership-applications'), $payload)
            ->assertJsonValidationErrors(['signature_name']);
    }

    public function test_unpublished_plans_unapproved_gyms_and_second_applications_are_refused(): void
    {
        $draft = MembershipPlan::factory()->forGym($this->gym)->create(['published_at' => null]);
        $this->actingAs($this->applicant)
            ->postJson($this->url('membership-applications'), $this->applicationPayload(['plan_id' => $draft->id]))
            ->assertJsonValidationErrors(['plan_id']);

        $pending = Gym::factory()->create();
        $this->actingAs($this->applicant)
            ->postJson("/api/gyms/{$pending->id}/membership-applications", $this->applicationPayload())
            ->assertNotFound();

        $this->apply();
        $this->actingAs($this->applicant)->postJson($this->url('membership-applications'), $this->applicationPayload())
            ->assertJsonValidationErrors(['plan_id']);

        // Gym Owner accounts cannot join a gym at all, so the type check answers before "you own this gym".
        $this->actingAs($this->owner)->postJson($this->url('membership-applications'), $this->applicationPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Only Gym Member accounts can do this.');
    }

    public function test_the_medical_file_is_private_and_health_answers_need_health_permission(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $payload = $this->applicationPayload(['health_answers' => ['recent_injury' => true]]);
        $payload['medical_file'] = UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf');

        $id = $this->actingAs($this->applicant)->post($this->url('membership-applications'), $payload, ['Accept' => 'application/json'])
            ->assertCreated()->json('membership.id');
        $membership = $this->membership($id);

        Storage::disk('local')->assertExists($membership->medical_file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertTrue($membership->health_answers['recent_injury']);

        $clerk = $this->employeeWith(['members' => 'approve']);
        $this->actingAs($clerk)->getJson($this->url("memberships/{$id}"))
            ->assertOk()
            ->assertJsonMissingPath('membership.health');
        $this->actingAs($clerk)->get($this->url("memberships/{$id}/medical-file"))->assertForbidden();

        $nurse = $this->employeeWith(['members' => 'view', 'health_records' => 'view']);
        $this->actingAs($nurse)->getJson($this->url("memberships/{$id}"))
            ->assertJsonPath('membership.health.answers.recent_injury', true)
            ->assertJsonPath('membership.health.emergency_contacts.0.name', 'Maria Cruz');
        $this->actingAs($nurse)->get($this->url("memberships/{$id}/medical-file"))->assertOk();
    }

    public function test_only_membership_approvers_or_the_owner_can_decide(): void
    {
        $membership = $this->apply();
        $manager = $this->employeeWith(['members' => 'manage']);

        $this->actingAs($manager)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])
            ->assertForbidden();

        $approver = $this->employeeWith(['members' => 'approve']);
        $this->actingAs($approver)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('membership.status', 'awaiting_payment');
    }

    public function test_rejection_needs_a_reason_the_applicant_sees(): void
    {
        Notification::fake();
        $membership = $this->apply();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'reject'])
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/decision"), [
            'decision' => 'reject', 'reason' => 'Class is full this month',
        ])->assertOk()->assertJsonPath('membership.status', 'rejected');

        Notification::assertSentTo($this->applicant, MembershipUpdated::class, fn ($n) => str_contains($n->toArray($this->applicant)['message'], 'Class is full this month'));

        $this->actingAs($this->applicant)->getJson("/api/my-memberships/{$membership->id}")
            ->assertJsonPath('membership.decision_reason', 'Class is full this month');

        // A rejected applicant may apply again later.
        $this->apply();
    }

    public function test_approval_creates_the_athlete_profile_and_waits_for_payment(): void
    {
        Notification::fake();
        $membership = $this->approved();

        $this->assertSame(MembershipStatus::AwaitingPayment, $membership->status);
        $this->assertSame('1500.00', $membership->amount_charged);
        $this->assertTrue($this->applicant->fresh()->hasGymRole($this->gym, GymPersonType::Member));
        Notification::assertSentTo($this->applicant, MembershipUpdated::class);

        app(CurrentGym::class)->set($this->gym);
        $profile = $this->gym->athleteProfiles()->where('user_id', $this->applicant->id)->firstOrFail();
        $this->assertSame('Two years of boxing.', $profile->background);
        $this->assertSame('Maria Cruz', $profile->emergency_contacts[0]['name']);

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/decision"), ['decision' => 'approve'])
            ->assertStatus(409);
    }

    public function test_a_free_plan_is_active_on_approval(): void
    {
        $this->plan->forceFill(['total_fee' => 0])->save();

        $this->assertSame(MembershipStatus::Active, $this->approved()->status);
    }

    public function test_staff_cannot_decide_their_own_application_and_other_gyms_see_nothing(): void
    {
        $approver = $this->employeeWith(['members' => 'approve']);
        $own = $this->apply($approver);

        $this->actingAs($approver)->postJson($this->url("memberships/{$own->id}/decision"), ['decision' => 'approve'])
            ->assertForbidden();

        $other = Gym::factory()->approved()->create();
        $other->addPerson($approver, GymPersonType::Owner);
        $this->actingAs($approver)->getJson("/api/gyms/{$other->id}/memberships/{$own->id}")->assertNotFound();
    }
}
