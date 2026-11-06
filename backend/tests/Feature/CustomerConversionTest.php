<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-71: manual and email links between customers and membership applications. */
class CustomerConversionTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
    }

    public function test_a_customer_links_manually_to_a_same_gym_application(): void
    {
        $customer = $this->addCustomer(['email' => 'manual.link@example.test']);
        $membership = $this->membershipFor($this->member);

        $this->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $membership->id])
            ->assertOk()
            ->assertJsonPath('customer.membership_id', $membership->id)
            ->assertJsonPath('customer.user_id', $this->member->id)
            ->assertJsonPath('customer.stage', 'applied');

        $this->assertNotNull($this->customer($customer->id)->linked_at);
        $this->assertSame($membership->id, $this->customer($customer->id)->membership_id);
    }

    public function test_creating_a_customer_auto_links_a_matching_application_case_insensitively(): void
    {
        $applicant = User::factory()->create(['email' => 'Auto.Link@Example.test']);
        $membership = $this->membershipFor($applicant);
        $this->actingAs($this->owner);

        $response = $this->postJson($this->url('customers'), $this->customerData([
            'name' => 'Auto Linked Lead',
            'email' => 'auto.link@example.test',
        ]))->assertCreated()
            ->assertJsonPath('customer.membership_id', $membership->id)
            ->assertJsonPath('customer.user_id', $applicant->id)
            ->assertJsonPath('customer.stage', 'applied');

        $this->assertNotNull($response->json('customer.linked_at'));

        // An application already linked to one customer is not linked to a second.
        $this->postJson($this->url('customers'), $this->customerData([
            'name' => 'Second Same Email',
            'email' => 'AUTO.LINK@example.test',
        ]))->assertCreated()
            ->assertJsonPath('customer.membership_id', null)
            ->assertJsonPath('customer.stage', 'lead');
    }

    public function test_submitting_an_application_links_an_existing_customer_by_email(): void
    {
        Notification::fake();
        $applicant = User::factory()->create([
            'first_name' => 'Case',
            'last_name' => 'Applicant',
            'email' => 'Case.Applicant@Example.test',
        ]);

        $customer = $this->addCustomer([
            'name' => 'Case Applicant',
            'email' => 'case.applicant@example.test',
            'user_id' => null,
        ]);
        $this->assertNull($customer->membership_id);
        $this->assertSame('lead', $customer->stage);

        $id = $this->actingAs($applicant)
            ->postJson("/api/gyms/{$this->gym->id}/membership-applications", $this->applicationPayload($applicant))
            ->assertCreated()
            ->assertJsonPath('membership.status', 'applied')
            ->json('membership.id');

        $customer->refresh();
        $this->assertSame($id, $customer->membership_id);
        $this->assertSame($applicant->id, $customer->user_id);
        $this->assertNotNull($customer->linked_at);
        $this->assertSame('applied', $customer->stage);
        $this->assertSame(MembershipStatus::Applied, Membership::withoutGlobalScope('gym')->findOrFail($id)->status);
    }

    public function test_another_gyms_application_cannot_be_linked(): void
    {
        $customer = $this->addCustomer(['email' => 'tenancy.link@example.test']);
        $otherGym = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $otherApplicant = User::factory()->create();
        $otherGym->addPerson($otherOwner, GymPersonType::Owner);

        app(CurrentGym::class)->set($otherGym);
        $otherPlan = MembershipPlan::factory()->forGym($otherGym)->create(['published_at' => now()]);
        $otherMembership = new Membership;
        $otherMembership->fill([
            'user_id' => $otherApplicant->id,
            'membership_plan_id' => $otherPlan->id,
            'emergency_contacts' => [['name' => 'Contact', 'relationship' => 'Friend', 'phone' => '0917 123 4567']],
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'waivers' => array_keys(config('membership.waivers')),
            'signature_name' => 'Other Applicant',
            'signed_on' => today()->toDateString(),
        ]);
        $otherMembership->forceFill(['status' => MembershipStatus::Applied->value])->save();
        app(CurrentGym::class)->set($this->gym);

        $this->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $otherMembership->id])
            ->assertNotFound();

        $this->assertNull($this->customer($customer->id)->membership_id);
    }

    public function test_the_linkable_memberships_route_exposes_only_this_gyms_applications(): void
    {
        $viewer = $this->employeeWithRole(['customers.records.view']);
        $membership = $this->membershipFor($this->member);

        $this->actingAs($viewer)->getJson($this->url('customers/linkable-memberships'))
            ->assertOk()
            ->assertJsonCount(1, 'memberships')
            ->assertJsonPath('memberships.0.id', $membership->id)
            ->assertJsonPath('memberships.0.status', 'applied')
            ->assertJsonPath('memberships.0.member_id', $this->member->id)
            ->assertJsonPath('memberships.0.member_name', 'Mia Torres')
            ->assertJsonPath('memberships.0.email', $this->member->email)
            ->assertJsonPath('memberships.0.plan_name', $this->plan->name);
    }

    private function applicationPayload(User $applicant): array
    {
        return [
            'plan_id' => $this->plan->id,
            'emergency_contacts' => [['name' => 'Maria Cruz', 'relationship' => 'Parent', 'phone' => '0917 123 4567']],
            'background' => 'New to boxing.',
            'current_rank' => 'None',
            'training_history' => 'No formal training yet.',
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'health_notes' => null,
            'waivers' => array_fill_keys(array_keys(config('membership.waivers')), true),
            'signature_name' => trim("{$applicant->first_name} {$applicant->last_name}"),
        ];
    }
}
