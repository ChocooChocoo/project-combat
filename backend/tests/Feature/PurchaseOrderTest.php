<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PurchaseStatusChanged;
use App\Support\Procurement\Receiving;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-77, R-79: approved requests become orders; direct buying, cancellation and short close. */
class PurchaseOrderTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
    }

    public function test_only_an_approved_request_can_become_one_order_with_quantities_and_costs(): void
    {
        $request = $this->makeRequest();
        $line = $request->lines->sole();

        $payload = [
            'purchase_request_id' => $request->id,
            'supplier_id' => $this->supplier->id,
            'expected_on' => '2026-11-20',
            'lines' => [[
                'purchase_request_line_id' => $line->id,
                'quantity' => 10,
                'unit_cost' => '90.00',
            ]],
        ];

        $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders'), $payload)
            ->assertStatus(409);

        $request = $this->approve($request);

        $withoutCost = $payload;
        $withoutCost['lines'][0]['unit_cost'] = null;
        $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders'), $withoutCost)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.unit_cost');

        $over = $payload;
        $over['lines'][0]['quantity'] = 11;
        $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders'), $over)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.quantity');

        $response = $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders'), $payload)
            ->assertCreated();

        $response->assertJsonPath('order.purchase_request_id', $request->id)
            ->assertJsonPath('order.supplier_id', $this->supplier->id)
            ->assertJsonPath('order.supplier_name', 'Manila Fight Gear Supply')
            ->assertJsonPath('order.is_direct', false)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.expected_on', '2026-11-20')
            ->assertJsonPath('order.total', '900.00')
            ->assertJsonPath('order.ordered_by_name', 'Owen Reyes');

        $this->assertMatchesRegularExpression('/^PO-'.$this->gym->id.'-\d{6}$/', $response->json('order.number'));

        $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders'), $payload)
            ->assertStatus(409);
        $this->assertSame(1, $this->gym->purchaseOrders()->count());
    }

    public function test_a_direct_purchase_is_owner_only_and_flagged(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'expected_on' => '2026-11-12',
            'lines' => [[
                'stock_item_id' => $this->item->id,
                'quantity' => 2,
                'unit_cost' => '80.00',
            ]],
        ];

        $this->actingAs($this->requester)
            ->postJson($this->url('purchase-orders/direct'), $payload)
            ->assertForbidden();

        $response = $this->actingAs($this->owner)
            ->postJson($this->url('purchase-orders/direct'), $payload)
            ->assertCreated();

        $response->assertJsonPath('order.is_direct', true)
            ->assertJsonPath('order.purchase_request_id', null)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.total', '160.00')
            ->assertJsonPath('order.expected_on', '2026-11-12');

        $order = $this->gym->purchaseOrders()->findOrFail($response->json('order.id'));
        $this->assertTrue($order->is_direct);
        $this->assertNull($order->purchase_request_id);
        $this->assertSame(2, $order->lines->sole()->quantity_ordered);
    }

    public function test_cancel_is_only_for_a_pending_order_with_nothing_received(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-orders/{$order->id}/close"), ['reason' => 'Nothing arrived'])
            ->assertStatus(409);

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-orders/{$order->id}/cancel"), ['reason' => 'Supplier could not deliver'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.cancel_reason', 'Supplier could not deliver');

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-orders/{$order->id}/cancel"), ['reason' => 'Again'])
            ->assertStatus(409);

        $delivered = $this->makeOrder($this->approve($this->makeRequest()));
        app(Receiving::class)->receive(
            $this->gym,
            $delivered,
            [['purchase_order_line_id' => $delivered->lines->sole()->id, 'quantity' => 2]],
            $this->owner,
            today(),
        );

        $this->actingAs($this->approver)
            ->postJson($this->url("purchase-orders/{$delivered->id}/cancel"), ['reason' => 'Too late'])
            ->assertStatus(409);
        $this->assertSame('partly_received', $delivered->refresh()->status->value);
    }

    public function test_close_short_is_only_for_a_partly_received_order(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));

        $this->actingAs($this->owner)
            ->postJson($this->url("purchase-orders/{$order->id}/close"), ['reason' => 'No more coming'])
            ->assertStatus(409);

        app(Receiving::class)->receive(
            $this->gym,
            $order,
            [['purchase_order_line_id' => $order->lines->sole()->id, 'quantity' => 4]],
            $this->owner,
            today(),
        );

        $this->actingAs($this->owner)
            ->postJson($this->url("purchase-orders/{$order->id}/close"), ['reason' => 'Supplier discontinued the rest'])
            ->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.closed_short', true)
            ->assertJsonPath('order.cancel_reason', 'Supplier discontinued the rest');

        $this->actingAs($this->owner)
            ->postJson($this->url("purchase-orders/{$order->id}/close"), ['reason' => 'Again'])
            ->assertStatus(409);
    }

    public function test_status_notices_go_to_the_request_people_and_creator_but_not_the_actor(): void
    {
        $request = $this->approve($this->makeRequest());

        $created = $this->actingAs($this->owner)->postJson($this->url('purchase-orders'), [
            'purchase_request_id' => $request->id,
            'supplier_id' => $this->supplier->id,
            'lines' => [[
                'purchase_request_line_id' => $request->lines->sole()->id,
                'quantity' => 10,
                'unit_cost' => '90.00',
            ]],
        ])->assertCreated()->json('order');

        $order = $this->gym->purchaseOrders()->findOrFail($created['id']);

        $this->assertSame(1, $this->notices($this->requester));
        $this->assertSame(0, $this->notices($this->owner));

        app(Receiving::class)->receive(
            $this->gym,
            $order,
            [['purchase_order_line_id' => $order->lines->sole()->id, 'quantity' => 4]],
            $this->approver,
            today(),
        );

        $this->assertSame(2, $this->notices($this->requester));
        $this->assertSame(1, $this->notices($this->owner));

        $this->actingAs($this->requester)
            ->postJson($this->url("purchase-orders/{$order->id}/close"), ['reason' => 'Balance not coming'])
            ->assertOk();

        $this->assertSame(2, $this->notices($this->requester));
        $this->assertSame(2, $this->notices($this->owner));
    }

    public function test_suppliers_can_be_added_edited_and_archived(): void
    {
        $created = $this->actingAs($this->approver)->postJson($this->url('suppliers'), $this->supplierData())
            ->assertCreated()
            ->assertJsonPath('supplier.name', 'Quezon Sports Medical Trading')
            ->assertJsonPath('supplier.contact_person', 'Nina Perez')
            ->assertJsonPath('supplier.status', 'active')
            ->json('supplier');

        $this->actingAs($this->approver)->putJson($this->url("suppliers/{$created['id']}"), [
            'notes' => 'Archived after the contract ended.',
            'status' => 'archived',
        ])->assertOk()
            ->assertJsonPath('supplier.status', 'archived')
            ->assertJsonPath('supplier.notes', 'Archived after the contract ended.');

        $this->actingAs($this->requester)->getJson($this->url('suppliers'))
            ->assertOk()
            ->assertJsonCount(2, 'suppliers')
            ->assertJsonFragment(['id' => $created['id'], 'status' => 'archived']);

        $this->actingAs($this->requester)->postJson($this->url('suppliers'), $this->supplierData(['name' => 'No access']))
            ->assertForbidden();

        $this->actingAs($this->owner)->postJson($this->url('purchase-orders/direct'), [
            'supplier_id' => $created['id'],
            'lines' => [['stock_item_id' => $this->item->id, 'quantity' => 1, 'unit_cost' => '10.00']],
        ])->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    }

    private function notices(User $user): int
    {
        return $user->notifications()->where('type', PurchaseStatusChanged::class)->count();
    }
}
