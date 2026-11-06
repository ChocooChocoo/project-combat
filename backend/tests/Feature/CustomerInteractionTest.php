<?php

namespace Tests\Feature;

use App\Models\CustomerInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-70: interaction history, concerns and their resolution. */
class CustomerInteractionTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:00:00'));
        $this->setUpCustomerGym();
    }

    public function test_all_four_interaction_kinds_are_kept_on_the_customer_timeline(): void
    {
        $customer = $this->addCustomer();
        $channels = ['phone', 'sms', 'email', 'messenger'];

        foreach (['interaction', 'feedback', 'concern', 'preference'] as $index => $kind) {
            $this->postJson($this->url("customers/{$customer->id}/interactions"), [
                'kind' => $kind,
                'channel' => $channels[$index],
                'body' => "Entry for {$kind}.",
                'occurred_at' => '2026-11-'.(10 + $index).' 14:30:00',
            ])->assertCreated()
                ->assertJsonPath('interaction.kind', $kind)
                ->assertJsonPath('interaction.channel', $channels[$index])
                ->assertJsonPath('interaction.recorded_by', $this->owner->id)
                ->assertJsonPath('interaction.recorded_by_name', 'Owen Reyes')
                ->assertJsonPath('interaction.resolved_at', null);

            $this->assertDatabaseHas('customer_interactions', [
                'gym_id' => $this->gym->id,
                'customer_id' => $customer->id,
                'kind' => $kind,
                'channel' => $channels[$index],
                'body' => "Entry for {$kind}.",
                'recorded_by' => $this->owner->id,
            ]);
        }

        $this->assertSame(4, CustomerInteraction::where('customer_id', $customer->id)->count());

        $response = $this->getJson($this->url("customers/{$customer->id}"))->assertOk()
            ->assertJsonCount(4, 'interactions');
        $kinds = collect($response->json('interactions'))->pluck('kind')->sort()->values()->all();
        $this->assertSame(['concern', 'feedback', 'interaction', 'preference'], $kinds);
    }

    public function test_only_a_concern_can_be_resolved_and_resolution_records_who_and_when(): void
    {
        $customer = $this->addCustomer();
        $manager = $this->employeeWithRole(['customers.records.manage']);

        $nonConcern = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'feedback', 'channel' => 'walk_in', 'body' => 'Enjoyed the trial class.',
        ])->assertCreated()->json('interaction.id');

        $this->actingAs($manager)
            ->postJson($this->url("interactions/{$nonConcern}/resolve"), ['resolution' => 'Thanks given.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');

        $concern = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'concern', 'channel' => 'email', 'body' => 'The shower water was cold.',
        ])->assertCreated()->json('interaction.id');

        $this->travelTo(Carbon::parse('2026-11-13 11:15:00'));
        $this->actingAs($manager)
            ->postJson($this->url("interactions/{$concern}/resolve"), ['resolution' => 'Heater repaired and checked.'])
            ->assertOk()
            ->assertJsonPath('interaction.resolved_by', $manager->id)
            ->assertJsonPath('interaction.resolved_by_name', trim("{$manager->first_name} {$manager->last_name}"))
            ->assertJsonPath('interaction.resolved_at', Carbon::parse('2026-11-13 11:15:00')->toIso8601String())
            ->assertJsonPath('interaction.resolution', 'Heater repaired and checked.');

        $this->assertDatabaseHas('customer_interactions', [
            'id' => $concern,
            'resolved_by' => $manager->id,
            'resolution' => 'Heater repaired and checked.',
        ]);

        $this->actingAs($manager)
            ->postJson($this->url("interactions/{$concern}/resolve"), ['resolution' => 'Replaced the heater.'])
            ->assertStatus(409);
    }

    public function test_interactions_are_append_only_apart_from_resolution_fields(): void
    {
        $customer = $this->addCustomer();
        $id = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'interaction', 'channel' => 'phone', 'body' => 'Called about class times.',
        ])->assertCreated()->json('interaction.id');

        $interaction = CustomerInteraction::withoutGlobalScope('gym')->findOrFail($id);

        $this->assertLogicFails(function () use ($interaction) {
            $interaction->body = 'Changed after the fact.';
            $interaction->save();
        });
        $this->assertLogicFails(fn () => $interaction->delete());
        $this->assertSame('Called about class times.', $interaction->refresh()->body);

        $concernId = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'concern', 'channel' => 'sms', 'body' => 'Asked for a refund update.',
        ])->assertCreated()->json('interaction.id');
        $concern = CustomerInteraction::withoutGlobalScope('gym')->findOrFail($concernId);

        $this->assertLogicFails(function () use ($concern) {
            $concern->kind = 'feedback';
            $concern->save();
        });

        $this->postJson($this->url("interactions/{$concernId}/resolve"), ['resolution' => 'Refund receipt sent.'])
            ->assertOk();
        $this->assertSame('Refund receipt sent.', $concern->refresh()->resolution);
        $this->assertSame(2, CustomerInteraction::where('customer_id', $customer->id)->count());
    }

    public function test_view_and_manage_permissions_gate_the_timeline(): void
    {
        $customer = $this->addCustomer();
        $viewer = $this->employeeWithRole(['customers.records.view']);
        $manager = $this->employeeWithRole(['customers.records.manage']);
        $concern = $this->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'concern', 'channel' => 'phone', 'body' => 'Noise after 9 PM.',
        ])->assertCreated()->json('interaction.id');

        $this->actingAs($viewer)->getJson($this->url("customers/{$customer->id}"))->assertOk()
            ->assertJsonCount(1, 'interactions');
        $this->actingAs($viewer)->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'interaction', 'channel' => 'phone', 'body' => 'Called.',
        ])->assertForbidden();
        $this->actingAs($viewer)->postJson($this->url("interactions/{$concern}/resolve"), [
            'resolution' => 'Handled.',
        ])->assertForbidden();

        $this->actingAs($manager)->postJson($this->url("customers/{$customer->id}/interactions"), [
            'kind' => 'preference', 'channel' => 'messenger', 'body' => 'Prefers mornings.',
        ])->assertCreated();
        $this->actingAs($manager)->postJson($this->url("interactions/{$concern}/resolve"), [
            'resolution' => 'Quiet hours posted.',
        ])->assertOk();

        $this->actingAs($this->member)
            ->postJson($this->url("customers/{$customer->id}/interactions"), [
                'kind' => 'interaction', 'channel' => 'phone', 'body' => 'No access.',
            ])->assertForbidden();
    }
}
