<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-78: partial and full deliveries raise stock and post one expense per receipt. */
class ReceivingTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
    }

    public function test_a_partial_receipt_raises_stock_leaves_the_order_open_and_posts_the_expense(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));
        $line = $order->lines->sole();

        $response = $this->actingAs($this->approver)->postJson(
            $this->url("purchase-orders/{$order->id}/receipts"),
            [
                'received_on' => '2026-11-06',
                'note' => 'First four pairs.',
                'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 4]],
            ],
        )->assertCreated();

        $response->assertJsonPath('order.status', 'partly_received')
            ->assertJsonPath('receipt.purchase_order_id', $order->id)
            ->assertJsonPath('receipt.received_on', '2026-11-06')
            ->assertJsonPath('receipt.received_by_name', 'Ava Cruz')
            ->assertJsonPath('receipt.note', 'First four pairs.')
            ->assertJsonPath('receipt.total', '360.00')
            ->assertJsonPath('receipt.lines.0.purchase_order_line_id', $line->id)
            ->assertJsonPath('receipt.lines.0.quantity', 4)
            ->assertJsonPath('receipt.lines.0.unit_cost', '90.00')
            ->assertJsonPath('receipt.lines.0.amount', '360.00');

        $this->assertMatchesRegularExpression('/^GR-'.$this->gym->id.'-\d{6}$/', $response->json('receipt.number'));

        $this->assertSame(9, $this->item->refresh()->quantity);
        $this->assertSame(4, $order->lines()->sole()->quantity_received);
        $this->assertSame('partly_received', $order->refresh()->status->value);
        $this->assertCountsMatch($this->item);

        $movement = StockMovement::query()
            ->where('goods_receipt_line_id', $response->json('receipt.lines.0.id'))
            ->sole();
        $this->assertSame('receipt', $movement->type->value);
        $this->assertSame(4, $movement->quantity);
        $this->assertSame($this->approver->id, $movement->actor_id);

        $expense = $this->gym->expenses()->sole();
        $this->assertSame('purchases', $expense->category);
        $this->assertSame('360.00', $expense->amount);
        $this->assertSame('2026-11-06', $expense->incurred_on->toDateString());
        $this->assertSame('goods_receipt', $expense->source_type);
        $this->assertSame($response->json('receipt.id'), $expense->source_id);
        $this->assertSame($this->approver->id, $expense->recorded_by);

        $this->actingAs($this->owner)
            ->getJson($this->url("purchase-orders/{$order->id}"))
            ->assertOk()
            ->assertJsonPath('order.status', 'partly_received')
            ->assertJsonPath('lines.0.quantity_ordered', 10)
            ->assertJsonPath('lines.0.quantity_received', 4)
            ->assertJsonPath('lines.0.remaining', 6)
            ->assertJsonPath('receipts.0.id', $response->json('receipt.id'))
            ->assertJsonPath('receipts.0.lines.0.quantity', 4)
            ->assertJsonCount(2, 'timeline');
    }

    public function test_an_over_receipt_is_refused_without_changing_anything(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));
        $line = $order->lines->sole();

        $this->actingAs($this->owner)->postJson(
            $this->url("purchase-orders/{$order->id}/receipts"),
            ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 11]]],
        )->assertUnprocessable();

        $this->assertSame(0, $this->gym->goodsReceipts()->count());
        $this->assertSame(0, $this->gym->expenses()->count());
        $this->assertSame(5, $this->item->refresh()->quantity);
        $this->assertSame(0, $order->lines()->sole()->quantity_received);
        $this->assertSame('pending', $order->refresh()->status->value);
        $this->assertCountsMatch($this->item);
    }

    public function test_a_full_receipt_completes_the_order_and_posts_one_expense(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));
        $line = $order->lines->sole();

        $this->actingAs($this->owner)->postJson(
            $this->url("purchase-orders/{$order->id}/receipts"),
            ['lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 10]]],
        )->assertCreated()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('receipt.total', '900.00');

        $this->assertSame(15, $this->item->refresh()->quantity);
        $this->assertSame(10, $order->lines()->sole()->quantity_received);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame(1, $this->gym->expenses()->count());
        $this->assertCountsMatch($this->item);
    }

    public function test_a_duplicate_submission_returns_the_same_receipt_once(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));
        $line = $order->lines->sole();
        $payload = [
            'received_on' => '2026-11-06',
            'note' => 'Same delivery retried.',
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 4]],
        ];

        $first = $this->actingAs($this->owner)
            ->postJson($this->url("purchase-orders/{$order->id}/receipts"), $payload)
            ->assertCreated();

        $second = $this->actingAs($this->owner)
            ->postJson($this->url("purchase-orders/{$order->id}/receipts"), $payload)
            ->assertCreated();

        $this->assertSame($first->json('receipt.id'), $second->json('receipt.id'));
        $this->assertSame(1, $this->gym->goodsReceipts()->count());
        $this->assertSame(1, $this->gym->expenses()->count());
        $this->assertSame(4, $order->lines()->sole()->quantity_received);
        $this->assertSame(9, $this->item->refresh()->quantity);
        $this->assertSame('partly_received', $order->refresh()->status->value);
        $this->assertCountsMatch($this->item);
    }

    public function test_a_second_delivery_can_finish_the_remaining_quantity_and_further_receipts_are_refused(): void
    {
        $order = $this->makeOrder($this->approve($this->makeRequest()));
        $line = $order->lines->sole();
        $endpoint = $this->url("purchase-orders/{$order->id}/receipts");

        $this->actingAs($this->owner)->postJson($endpoint, [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 8]],
        ])->assertCreated();

        $this->actingAs($this->owner)->postJson($endpoint, [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 3]],
        ])->assertUnprocessable();

        $this->actingAs($this->owner)->postJson($endpoint, [
            'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.status', 'completed');

        $this->assertSame(15, $this->item->refresh()->quantity);
        $this->assertSame(2, $this->gym->goodsReceipts()->count());
        $this->assertSame(2, Expense::query()->count());
        $this->assertCountsMatch($this->item);
    }
}
