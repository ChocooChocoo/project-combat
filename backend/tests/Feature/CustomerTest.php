<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-69 and R-71: customer records, derived stages and staff assignments. */
class CustomerTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
    }

    public function test_a_lead_is_added_with_contact_details_and_becomes_a_customer_when_linked_and_started(): void
    {
        $response = $this->actingAs($this->owner)->postJson($this->url('customers'), $this->customerData([
            'email' => 'rico.dizon@example.test',
            'inquiry' => 'Wants a free trial before committing to a monthly plan.',
        ]))->assertCreated()
            ->assertJsonPath('customer.name', 'Rico Dizon')
            ->assertJsonPath('customer.email', 'rico.dizon@example.test')
            ->assertJsonPath('customer.phone', '0917 555 0171')
            ->assertJsonPath('customer.source', 'walk_in')
            ->assertJsonPath('customer.inquiry', 'Wants a free trial before committing to a monthly plan.')
            ->assertJsonPath('customer.status', 'open')
            ->assertJsonPath('customer.stage', 'lead')
            ->assertJsonPath('customer.assigned_to', $this->employee->id)
            ->assertJsonPath('customer.assigned_to_name', 'Ella Santos');

        $lead = $this->customer($response->json('customer.id'));
        $this->assertSame($this->owner->id, $lead->created_by);

        $membership = $this->membershipFor($this->member);
        $this->actingAs($this->owner)
            ->postJson($this->url("customers/{$lead->id}/link-membership"), ['membership_id' => $membership->id])
            ->assertOk()
            ->assertJsonPath('customer.membership_id', $membership->id)
            ->assertJsonPath('customer.user_id', $this->member->id)
            ->assertJsonPath('customer.stage', 'applied');

        $this->assertNotNull($this->customer($lead->id)->linked_at);

        $this->getJson($this->url("customers/{$lead->id}"))
            ->assertOk()
            ->assertJsonPath('customer.stage', 'applied');

        $this->activateMembership($membership);

        $this->getJson($this->url("customers/{$lead->id}"))
            ->assertOk()
            ->assertJsonPath('customer.stage', 'member');

        $this->getJson($this->url('customers?stage=member'))
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.id', $lead->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_lost_overrides_the_derived_stage_and_can_be_reopened(): void
    {
        $customer = $this->addCustomer(['email' => 'nadia.cruz@example.test']);
        $membership = $this->membershipFor($this->member);
        $this->actingAs($this->owner)
            ->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $membership->id])
            ->assertOk();
        $this->activateMembership($membership);
        $this->assertSame('member', $this->customer($customer->id)->stage);

        $this->putJson($this->url("customers/{$customer->id}"), ['status' => 'lost'])
            ->assertOk()
            ->assertJsonPath('customer.status', 'lost')
            ->assertJsonPath('customer.stage', 'lost');

        $this->putJson($this->url("customers/{$customer->id}"), ['status' => 'open'])
            ->assertOk()
            ->assertJsonPath('customer.status', 'open')
            ->assertJsonPath('customer.stage', 'member');
    }

    public function test_the_assignee_must_be_the_owner_or_an_employee_of_this_gym(): void
    {
        $otherGym = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $otherGym->addPerson($otherOwner, GymPersonType::Owner);

        $this->actingAs($this->owner);
        foreach ([$this->member->id, $otherOwner->id] as $invalid) {
            $this->postJson($this->url('customers'), $this->customerData(['assigned_to' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('assigned_to');
        }

        foreach ([$this->owner->id, $this->employee->id] as $valid) {
            $this->postJson($this->url('customers'), $this->customerData([
                'email' => "assignee.{$valid}@example.test",
                'assigned_to' => $valid,
            ]))->assertCreated()->assertJsonPath('customer.assigned_to', $valid);
        }

        $customer = $this->addCustomer(['email' => 'other.assignee@example.test']);
        $this->putJson($this->url("customers/{$customer->id}"), ['assigned_to' => $this->member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_the_list_search_and_stage_and_assignee_filters_have_the_frontend_shape(): void
    {
        $lead = $this->addCustomer([
            'name' => 'Rico Dizon',
            'email' => 'rico.dizon@example.test',
            'assigned_to' => $this->employee->id,
        ]);
        $customer = $this->addCustomer([
            'name' => 'Nadia Cruz',
            'email' => 'nadia.cruz@example.test',
            'assigned_to' => $this->owner->id,
        ]);
        $membership = $this->membershipFor($this->member);
        $this->postJson($this->url("customers/{$customer->id}/link-membership"), ['membership_id' => $membership->id])
            ->assertOk();

        $all = $this->getJson($this->url('customers'))->assertOk()->assertJsonCount(2, 'customers');
        $all->assertJsonStructure(['customers' => [[
            'id', 'name', 'email', 'phone', 'source', 'inquiry', 'user_id', 'membership_id',
            'linked_at', 'status', 'stage', 'assigned_to', 'assigned_to_name', 'created_at', 'updated_at',
        ]]]);

        $this->getJson($this->url('customers?search=rico'))
            ->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $lead->id);
        $this->getJson($this->url('customers?stage=lead'))
            ->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $lead->id);
        $this->getJson($this->url('customers?stage=applied'))
            ->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $customer->id);
        $this->getJson($this->url("customers?assigned_to={$this->employee->id}"))
            ->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.id', $lead->id);
        $this->getJson($this->url('customers?search=nobody'))
            ->assertOk()->assertJsonCount(0, 'customers');
    }

    public function test_view_and_manage_permissions_gate_each_action(): void
    {
        $viewer = $this->employeeWithRole(['customers.records.view']);
        $manager = $this->employeeWithRole(['customers.records.manage']);
        $customer = $this->addCustomer();

        $this->actingAs($viewer)->getJson($this->url('customers'))->assertOk()->assertJsonCount(1, 'customers');
        $this->actingAs($viewer)->getJson($this->url("customers/{$customer->id}"))->assertOk();
        $this->actingAs($viewer)->getJson($this->url('follow-ups'))->assertOk();
        $this->actingAs($viewer)->getJson($this->url('customer-groups'))->assertOk();
        $this->actingAs($viewer)->postJson($this->url('customers'), $this->customerData())->assertForbidden();
        $this->actingAs($viewer)->putJson($this->url("customers/{$customer->id}"), ['status' => 'lost'])->assertForbidden();
        $this->actingAs($viewer)->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'interaction', 'channel' => 'phone', 'body' => 'Called.',
        ])->assertForbidden();
        $this->actingAs($viewer)->postJson($this->url('customer-groups'), ['name' => 'Beginners'])->assertForbidden();

        $this->actingAs($manager)->postJson($this->url('customers'), $this->customerData(['email' => 'managed@example.test']))
            ->assertCreated();
        $this->actingAs($manager)->putJson($this->url("customers/{$customer->id}"), ['status' => 'lost'])->assertOk();
        $this->actingAs($manager)->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'interaction', 'channel' => 'phone', 'body' => 'Called.',
        ])->assertCreated();
        $this->actingAs($manager)->getJson($this->url('customers'))->assertForbidden();

        $this->actingAs($this->member)->getJson($this->url('customers'))->assertForbidden();
    }
}
