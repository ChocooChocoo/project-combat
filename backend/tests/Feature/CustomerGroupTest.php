<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\CustomerGroup;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-71: named customer groups, unique names and member sync. */
class CustomerGroupTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
        $this->actingAs($this->owner);
    }

    public function test_group_names_are_unique_per_gym_and_groups_can_be_edited_and_deleted(): void
    {
        $group = $this->postJson($this->url('customer-groups'), [
            'name' => 'Beginners class',
            'description' => 'New members and trial leads.',
        ])->assertCreated()
            ->assertJsonPath('group.name', 'Beginners class')
            ->assertJsonPath('group.members_count', 0)
            ->json('group.id');

        $this->postJson($this->url('customer-groups'), ['name' => 'Beginners class'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->putJson($this->url("customer-groups/{$group}"), [
            'name' => 'Beginners class',
            'description' => 'Updated description.',
        ])->assertOk()->assertJsonPath('group.description', 'Updated description.');

        $otherGym = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $otherGym->addPerson($otherOwner, GymPersonType::Owner);
        $this->actingAs($otherOwner)
            ->postJson("/api/gyms/{$otherGym->id}/customer-groups", ['name' => 'Beginners class'])
            ->assertCreated();

        $this->actingAs($this->owner);
        $this->getJson($this->url('customer-groups'))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.id', $group);

        $this->deleteJson($this->url("customer-groups/{$group}"))->assertNoContent();
        $this->assertDatabaseMissing('customer_groups', ['id' => $group]);
        $this->assertSame(0, DB::table('customer_group_members')->where('customer_group_id', $group)->count());
    }

    public function test_member_sync_replaces_the_whole_set_and_updates_the_count(): void
    {
        $group = $this->postJson($this->url('customer-groups'), ['name' => 'Announcement list'])
            ->assertCreated()->json('group.id');
        $first = $this->addCustomer(['name' => 'First Customer', 'email' => 'first.group@example.test']);
        $second = $this->addCustomer(['name' => 'Second Customer', 'email' => 'second.group@example.test']);

        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'customer_ids' => [$first->id, $second->id],
        ])->assertOk()
            ->assertJsonPath('group.members_count', 2)
            ->assertJsonPath('group.customer_ids', [$first->id, $second->id])
            ->assertJsonCount(2, 'members');

        $this->assertDatabaseCount('customer_group_members', 2);

        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'members' => [$second->id],
        ])->assertOk()
            ->assertJsonPath('group.members_count', 1)
            ->assertJsonPath('group.customer_ids', [$second->id]);

        $this->assertDatabaseHas('customer_group_members', ['customer_group_id' => $group, 'customer_id' => $second->id]);
        $this->assertDatabaseMissing('customer_group_members', ['customer_group_id' => $group, 'customer_id' => $first->id]);

        $this->putJson($this->url("customer-groups/{$group}/members"), ['customer_ids' => []])
            ->assertOk()
            ->assertJsonPath('group.members_count', 0);
        $this->assertSame(0, CustomerGroup::findOrFail($group)->members()->count());
    }

    public function test_adding_a_linked_user_reuses_their_customer_row_and_creates_no_duplicate(): void
    {
        $group = $this->postJson($this->url('customer-groups'), ['name' => 'Reachable members'])
            ->assertCreated()->json('group.id');
        $linked = $this->addCustomer([
            'name' => 'Mia Torres',
            'email' => $this->member->email,
            'user_id' => $this->member->id,
        ]);

        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'user_ids' => [$this->member->id],
        ])->assertOk()
            ->assertJsonPath('group.members_count', 1)
            ->assertJsonPath('group.customer_ids', [$linked->id]);

        $this->assertSame(1, $this->customerCount());

        // An employee with no customer row gets exactly one, linked by user_id.
        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'user_ids' => [$this->member->id, $this->employee->id],
        ])->assertOk()
            ->assertJsonPath('group.members_count', 2);

        $this->assertSame(2, $this->customerCount());
        $created = $this->customer(
            DB::table('customers')->where('gym_id', $this->gym->id)->where('user_id', $this->employee->id)->value('id')
        );
        $this->assertSame('Ella Santos', $created->name);
        $this->assertSame('other', $created->source->value);
        $this->assertSame($this->owner->id, $created->created_by);

        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'user_ids' => [$this->member->id, $this->employee->id],
        ])->assertOk()->assertJsonPath('group.members_count', 2);
        $this->assertSame(2, $this->customerCount());
    }

    public function test_a_converted_lead_appears_as_a_member_in_a_customer_group(): void
    {
        $customer = $this->addCustomer([
            'name' => 'Mia Torres',
            'email' => $this->member->email,
            'user_id' => $this->member->id,
        ]);
        $membership = $this->membershipFor($this->member);

        $this->postJson($this->url("customers/{$customer->id}/link-membership"), [
            'membership_id' => $membership->id,
        ])->assertOk()->assertJsonPath('customer.stage', 'applied');

        $this->activateMembership($membership);
        $this->assertSame('member', $this->customer($customer->id)->stage);

        $group = $this->postJson($this->url('customer-groups'), ['name' => 'Converted leads'])
            ->assertCreated()->json('group.id');

        $this->putJson($this->url("customer-groups/{$group}/members"), [
            'customer_ids' => [$customer->id],
        ])->assertOk()
            ->assertJsonPath('group.members_count', 1)
            ->assertJsonPath('group.members.0.id', $customer->id)
            ->assertJsonPath('group.members.0.stage', 'member');
    }

    public function test_group_permissions_separate_view_from_group_management(): void
    {
        $viewer = $this->employeeWithRole(['customers.records.view']);
        $groupsManager = $this->employeeWithRole(['customers.records.view', 'customers.groups.manage']);
        $recordsManager = $this->employeeWithRole(['customers.records.manage']);

        $this->actingAs($viewer)->getJson($this->url('customer-groups'))->assertOk();
        $this->actingAs($viewer)->postJson($this->url('customer-groups'), ['name' => 'Viewer group'])->assertForbidden();

        $group = $this->actingAs($groupsManager)->postJson($this->url('customer-groups'), ['name' => 'Manager group'])
            ->assertCreated()->json('group.id');
        $this->actingAs($groupsManager)->putJson($this->url("customer-groups/{$group}"), [
            'name' => 'Manager group renamed',
        ])->assertOk();
        $customer = $this->addCustomer();
        $this->actingAs($groupsManager)->putJson($this->url("customer-groups/{$group}/members"), [
            'customer_ids' => [$customer->id],
        ])->assertOk();
        $this->actingAs($viewer)->putJson($this->url("customer-groups/{$group}/members"), [
            'customer_ids' => [$customer->id],
        ])->assertForbidden();
        $this->actingAs($viewer)->deleteJson($this->url("customer-groups/{$group}"))->assertForbidden();

        $this->actingAs($groupsManager)->deleteJson($this->url("customer-groups/{$group}"))->assertNoContent();

        $this->actingAs($recordsManager)->postJson($this->url('customer-groups'), ['name' => 'Records only'])
            ->assertForbidden();
    }
}
