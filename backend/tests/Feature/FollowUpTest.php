<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\FollowUpAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-69: follow-up scheduling, reassignment, completion, cancellation and notice. */
class FollowUpTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
    }

    public function test_create_reassign_complete_and_cancel_with_the_assignee_notified(): void
    {
        Notification::fake();
        $customer = $this->addCustomer();

        $response = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-15 10:00:00',
            'note' => 'Confirm the private coaching add-on.',
        ])->assertCreated()
            ->assertJsonPath('followUp.customer_id', $customer->id)
            ->assertJsonPath('followUp.customer_name', $customer->name)
            ->assertJsonPath('followUp.assigned_to', $this->employee->id)
            ->assertJsonPath('followUp.assigned_to_name', 'Ella Santos')
            ->assertJsonPath('followUp.status', 'open')
            ->assertJsonPath('followUp.due_at', Carbon::parse('2026-11-15 10:00:00')->toIso8601String())
            ->assertJsonPath('followUp.note', 'Confirm the private coaching add-on.')
            ->assertJsonPath('followUp.done_at', null);

        $followUp = $response->json('followUp.id');
        Notification::assertSentTo($this->employee, FollowUpAssigned::class, function (FollowUpAssigned $notification) use ($customer, $followUp) {
            $data = $notification->toArray($this->employee);

            return $notification->via($this->employee) === ['database']
                && $data['customer_id'] === $customer->id
                && $data['follow_up_id'] === $followUp
                && $data['customer_name'] === $customer->name;
        });

        $this->putJson($this->url("follow-ups/{$followUp}"), ['assigned_to' => $this->owner->id])
            ->assertOk()
            ->assertJsonPath('followUp.assigned_to', $this->owner->id)
            ->assertJsonPath('followUp.assigned_to_name', 'Owen Reyes');
        Notification::assertSentTo($this->owner, FollowUpAssigned::class);

        $this->putJson($this->url("follow-ups/{$followUp}"), [
            'status' => 'done',
            'outcome' => 'Member added two private sessions.',
        ])->assertOk()
            ->assertJsonPath('followUp.status', 'done')
            ->assertJsonPath('followUp.done_by', $this->owner->id)
            ->assertJsonPath('followUp.done_by_name', 'Owen Reyes')
            ->assertJsonPath('followUp.done_at', Carbon::parse('2026-11-13 09:00:00')->toIso8601String())
            ->assertJsonPath('followUp.outcome', 'Member added two private sessions.');

        $this->putJson($this->url("follow-ups/{$followUp}"), ['status' => 'cancelled'])
            ->assertStatus(409);

        $second = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-18 09:00:00',
            'note' => 'Ask for a referral.',
        ])->assertCreated()->json('followUp.id');

        $this->putJson($this->url("follow-ups/{$second}"), [
            'status' => 'cancelled',
            'outcome' => 'Member asked to be contacted next month.',
        ])->assertOk()
            ->assertJsonPath('followUp.status', 'cancelled')
            ->assertJsonPath('followUp.done_at', null)
            ->assertJsonPath('followUp.outcome', 'Member asked to be contacted next month.');

        $this->putJson($this->url("follow-ups/{$second}"), ['status' => 'done', 'outcome' => 'Called.'])
            ->assertStatus(409);
    }

    public function test_done_requires_an_outcome_and_self_assignment_is_allowed(): void
    {
        Notification::fake();
        $customer = $this->addCustomer(['assigned_to' => $this->employee->id]);

        $defaultAssignee = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'due_at' => '2026-11-16 09:00:00',
            'note' => 'Use the customer default owner.',
        ])->assertCreated()->assertJsonPath('followUp.assigned_to', $this->employee->id)
            ->json('followUp.id');

        $this->putJson($this->url("follow-ups/{$defaultAssignee}"), ['status' => 'done'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('outcome');

        $self = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->owner->id,
            'due_at' => '2026-11-17 09:00:00',
            'note' => 'Owner will call personally.',
        ])->assertCreated()->assertJsonPath('followUp.assigned_to', $this->owner->id)
            ->json('followUp.id');

        Notification::assertSentTo($this->owner, FollowUpAssigned::class);
        $this->putJson($this->url("follow-ups/{$self}"), [
            'status' => 'done',
            'outcome' => 'Called and confirmed the plan.',
        ])->assertOk();
    }

    public function test_the_assignee_must_be_this_gyms_owner_or_employee(): void
    {
        $customer = $this->addCustomer();
        $otherGym = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $otherGym->addPerson($otherOwner, GymPersonType::Owner);

        foreach ([$this->member->id, $otherOwner->id] as $invalid) {
            $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
                'assigned_to' => $invalid,
                'due_at' => '2026-11-16 09:00:00',
                'note' => 'Not a staff member.',
            ])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        }

        $followUp = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-16 09:00:00',
            'note' => 'Valid assignment.',
        ])->assertCreated()->json('followUp.id');

        $this->putJson($this->url("follow-ups/{$followUp}"), ['assigned_to' => $this->member->id])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }

    public function test_the_follow_up_list_filters_by_status_and_assignee_with_open_rows_first(): void
    {
        $customer = $this->addCustomer();
        $open = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-15 10:00:00',
            'note' => 'Open employee follow-up.',
        ])->assertCreated()->json('followUp.id');
        $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->owner->id,
            'due_at' => '2026-11-14 10:00:00',
            'note' => 'Open owner follow-up.',
        ])->assertCreated();
        $done = $this->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-12 10:00:00',
            'note' => 'Done employee follow-up.',
        ])->assertCreated()->json('followUp.id');
        $this->putJson($this->url("follow-ups/{$done}"), [
            'status' => 'done', 'outcome' => 'Completed before the list check.',
        ])->assertOk();

        $this->getJson($this->url('follow-ups'))
            ->assertOk()
            ->assertJsonCount(3, 'followUps')
            ->assertJsonPath('followUps.0.status', 'open')
            ->assertJsonPath('followUps.1.status', 'open')
            ->assertJsonPath('followUps.2.id', $done)
            ->assertJsonStructure(['followUps' => [[
                'id', 'customer_id', 'customer_name', 'assigned_to', 'assigned_to_name', 'due_at',
                'note', 'status', 'done_at', 'done_by', 'outcome',
            ]]]);

        $this->getJson($this->url("follow-ups?status=open&assigned_to={$this->employee->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'followUps')
            ->assertJsonPath('followUps.0.id', $open)
            ->assertJsonPath('followUps.0.customer_name', $customer->name);

        $this->getJson($this->url('follow-ups?status=done&assigned_to='.$this->owner->id))
            ->assertOk()
            ->assertJsonCount(0, 'followUps');
    }

    public function test_view_and_manage_permissions_gate_follow_ups(): void
    {
        $customer = $this->addCustomer();
        $viewer = $this->employeeWithRole(['customers.records.view']);
        $manager = $this->employeeWithRole(['customers.records.manage']);

        $this->actingAs($viewer)->getJson($this->url('follow-ups'))->assertOk();
        $this->actingAs($viewer)->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-15 10:00:00',
            'note' => 'Viewer cannot add.',
        ])->assertForbidden();

        $followUp = $this->actingAs($manager)->postJson($this->url("customers/{$customer->id}/follow-ups"), [
            'assigned_to' => $this->employee->id,
            'due_at' => '2026-11-15 10:00:00',
            'note' => 'Manager can add.',
        ])->assertCreated()->json('followUp.id');

        $this->actingAs($viewer)->putJson($this->url("follow-ups/{$followUp}"), ['status' => 'cancelled'])
            ->assertForbidden();
        $this->actingAs($manager)->putJson($this->url("follow-ups/{$followUp}"), ['status' => 'cancelled'])
            ->assertOk();
        $this->actingAs($manager)->getJson($this->url('follow-ups'))->assertForbidden();
    }
}
