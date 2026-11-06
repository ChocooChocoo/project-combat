<?php

namespace Tests\Feature;

use App\Notifications\PurchaseRequestDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-76: requests, lines and a different permitted approver. */
class PurchaseRequestTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
    }

    public function test_a_request_is_created_with_its_lines_and_number(): void
    {
        $need = $this->gym->supplyNeeds()->create([
            'stock_item_id' => $this->item->id,
            'needed_by' => today()->addDays(4)->toDateString(),
            'quantity' => 10,
            'status' => 'open',
            'created_by' => $this->requester->id,
        ]);

        $response = $this->actingAs($this->requester)->postJson($this->url('purchase-requests'), [
            'needed_by' => today()->addDays(8)->toDateString(),
            'note' => 'For the weekend classes.',
            'lines' => [
                ['stock_item_id' => $this->item->id, 'quantity' => 10, 'est_unit_cost' => '100.00', 'supply_need_id' => $need->id],
            ],
        ])->assertCreated();

        $response->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.requested_by', $this->requester->id)
            ->assertJsonPath('request.requested_by_name', 'Rhea Santos')
            ->assertJsonPath('request.needed_by', today()->addDays(8)->toDateString())
            ->assertJsonPath('request.lines.0.stock_item_id', $this->item->id)
            ->assertJsonPath('request.lines.0.item_name', 'Boxing gloves 14 oz')
            ->assertJsonPath('request.lines.0.quantity', 10)
            ->assertJsonPath('request.lines.0.est_unit_cost', '100.00')
            ->assertJsonPath('request.lines.0.supply_need_id', $need->id);

        $this->assertMatchesRegularExpression(
            '/^PR-'.$this->gym->id.'-\d{6}$/',
            $response->json('request.number'),
        );
        $this->assertSame('requested', $need->refresh()->status->value);
        $this->assertDatabaseHas('purchase_request_lines', [
            'purchase_request_id' => $response->json('request.id'),
            'stock_item_id' => $this->item->id,
            'quantity' => 10,
        ]);
    }

    public function test_the_requester_cannot_approve_even_with_permission_and_even_when_they_own_the_gym(): void
    {
        $request = $this->makeRequest();
        $id = $request->id;

        $this->actingAs($this->requester)
            ->postJson($this->url("purchase-requests/{$id}/decision"), ['decision' => 'approved'])
            ->assertForbidden();
        $this->assertSame('pending', $request->refresh()->status->value);

        $ownerRequest = $this->makeRequest(requester: $this->owner);
        $this->actingAs($this->owner)
            ->postJson($this->url("purchase-requests/{$ownerRequest->id}/decision"), ['decision' => 'approved'])
            ->assertForbidden();
        $this->assertSame('pending', $ownerRequest->refresh()->status->value);

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-requests/{$id}/decision"), [
                'decision' => 'approved', 'decision_reason' => 'Approved for this block.',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.decided_by_name', 'Ava Cruz')
            ->assertJsonPath('request.decision_reason', 'Approved for this block.');

        $this->assertNotNull($request->refresh()->decided_at);
        $this->assertSame($this->approver->id, $request->decided_by);
        $this->assertSame(1, $this->requester->notifications()->where('type', PurchaseRequestDecided::class)->count());
        $this->assertSame(0, $this->approver->notifications()->where('type', PurchaseRequestDecided::class)->count());
    }

    public function test_a_rejection_requires_a_reason(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-requests/{$request->id}/decision"), ['decision' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision_reason');

        $this->assertSame('pending', $request->refresh()->status->value);

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-requests/{$request->id}/decision"), [
                'decision' => 'rejected',
                'decision_reason' => 'Buy after the tournament.',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'rejected')
            ->assertJsonPath('request.decision_reason', 'Buy after the tournament.');

        $this->assertSame(1, $this->requester->notifications()->where('type', PurchaseRequestDecided::class)->count());
        $this->assertStringContainsString(
            'Buy after the tournament.',
            (string) $this->requester->notifications()->where('type', PurchaseRequestDecided::class)->sole()->data['message'],
        );
    }

    public function test_only_a_pending_request_can_be_cancelled(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->requester)
            ->postJson($this->url("purchase-requests/{$request->id}/cancel"), ['reason' => 'Ordered elsewhere.'])
            ->assertOk()
            ->assertJsonPath('request.status', 'cancelled')
            ->assertJsonPath('request.decision_reason', 'Ordered elsewhere.');

        $this->actingAs($this->requester)
            ->postJson($this->url("purchase-requests/{$request->id}/cancel"), ['reason' => 'Again'])
            ->assertStatus(409);

        $approved = $this->approve($this->makeRequest());
        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-requests/{$approved->id}/cancel"), ['reason' => 'Too late'])
            ->assertStatus(409);
        $this->assertSame('approved', $approved->refresh()->status->value);
    }

    public function test_a_rejected_request_returns_its_supply_need_to_open(): void
    {
        $need = $this->gym->supplyNeeds()->create([
            'stock_item_id' => $this->item->id,
            'needed_by' => today()->addDays(4)->toDateString(),
            'quantity' => 10,
            'status' => 'open',
            'created_by' => $this->requester->id,
        ]);

        $created = $this->actingAs($this->requester)->postJson($this->url('purchase-requests'), [
            'lines' => [[
                'stock_item_id' => $this->item->id, 'quantity' => 10, 'est_unit_cost' => '100.00',
                'supply_need_id' => $need->id,
            ]],
        ])->assertCreated()->json('request');

        $this->assertSame('requested', $need->refresh()->status->value);

        $this->actingAs($this->approver)->postJson($this->url("purchase-requests/{$created['id']}/decision"), [
            'decision' => 'rejected', 'decision_reason' => 'Wait for the next budget.',
        ])->assertOk();

        $this->assertSame('open', $need->refresh()->status->value);
        $this->assertSame('rejected', $this->gym->purchaseRequests()->findOrFail($created['id'])->status->value);
    }
}
