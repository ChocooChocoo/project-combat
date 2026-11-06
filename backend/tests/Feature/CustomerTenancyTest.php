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
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-09: CRM records and applications never cross a gym boundary. */
class CustomerTenancyTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
    }

    public function test_another_gyms_customer_follow_up_group_and_application_all_return_404(): void
    {
        $customer = $this->addCustomer(['email' => 'tenancy.customer@example.test']);
        $interaction = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'concern', 'channel' => 'phone', 'body' => 'Private concern for gym A.',
        ])->assertCreated()->json('interaction.id');
        $followUp = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-16 09:00:00',
            'note' => 'Private follow-up for gym A.',
        ])->assertCreated()->json('followUp.id');
        $group = $this->postJson($this->url('customer-groups'), ['name' => 'Gym A audience'])
            ->assertCreated()->json('group.id');
        $aMembership = $this->membershipFor($this->member);

        $otherGym = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create(['first_name' => 'Other', 'last_name' => 'Owner']);
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

        $base = "/api/gyms/{$otherGym->id}";
        $this->actingAs($otherOwner)->getJson("{$base}/customers/{$customer->id}")->assertNotFound();
        $this->actingAs($otherOwner)->putJson("{$base}/customers/{$customer->id}", ['status' => 'lost'])->assertNotFound();
        $this->actingAs($otherOwner)->postJson("{$base}/customers/{$customer->id}/interactions", [
            'kind' => 'interaction', 'channel' => 'phone', 'body' => 'Cross-gym write.',
        ])->assertNotFound();
        $this->actingAs($otherOwner)->postJson("{$base}/interactions/{$interaction}/resolve", [
            'resolution' => 'Cross-gym resolve.',
        ])->assertNotFound();
        $this->actingAs($otherOwner)->postJson("{$base}/customers/{$customer->id}/follow-ups", [
            'assigned_to' => $otherOwner->id, 'due_at' => '2026-11-16 09:00:00', 'note' => 'Cross-gym follow-up.',
        ])->assertNotFound();
        $this->actingAs($otherOwner)->putJson("{$base}/follow-ups/{$followUp}", ['status' => 'cancelled'])
            ->assertNotFound();
        $this->actingAs($otherOwner)->putJson("{$base}/customer-groups/{$group}", ['name' => 'Hijacked'])
            ->assertNotFound();
        $this->actingAs($otherOwner)->deleteJson("{$base}/customer-groups/{$group}")->assertNotFound();
        $this->actingAs($otherOwner)->putJson("{$base}/customer-groups/{$group}/members", [
            'customer_ids' => [$customer->id],
        ])->assertNotFound();

        $this->actingAs($otherOwner)->getJson("{$base}/customers")->assertOk()->assertJsonCount(0, 'customers');
        $this->actingAs($otherOwner)->getJson("{$base}/follow-ups")->assertOk()->assertJsonCount(0, 'followUps');
        $this->actingAs($otherOwner)->getJson("{$base}/customer-groups")->assertOk()->assertJsonCount(0, 'groups');
        $this->actingAs($otherOwner)->getJson("{$base}/customers/linkable-memberships")
            ->assertOk()
            ->assertJsonCount(1, 'memberships')
            ->assertJsonPath('memberships.0.id', $otherMembership->id);

        $this->actingAs($this->owner)
            ->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $otherMembership->id])
            ->assertNotFound();
        $this->assertNull($this->customer($customer->id)->membership_id);

        $this->actingAs($this->owner)
            ->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $aMembership->id])
            ->assertOk();

        $this->assertSame($aMembership->id, $this->customer($customer->id)->membership_id);
        $this->getJson($this->url("customers/{$customer->id}"))->assertOk()->assertJsonCount(1, 'interactions');
        $this->getJson($this->url('follow-ups'))->assertOk()->assertJsonCount(1, 'followUps');
        $this->getJson($this->url('customer-groups'))->assertOk()->assertJsonCount(1, 'groups');
    }
}
