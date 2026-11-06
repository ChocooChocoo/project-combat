<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\StockSetup;
use Tests\TestCase;

/** R-63: walk-in and merchandise desk sales. */
class SaleTest extends TestCase
{
    use RefreshDatabase, StockSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->setUpStockGym();
    }

    public function test_a_walk_in_sale_records_income_with_no_user(): void
    {
        $response = $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'walk_in',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'amount' => 500,
            'discount' => 50,
            'note' => 'Day pass',
        ])->assertCreated()
            ->assertJsonPath('payment.type', 'walk_in')
            ->assertJsonPath('payment.user_id', null)
            ->assertJsonPath('payment.member_name', null)
            ->assertJsonPath('payment.payer_name', 'Walk-in guest')
            ->assertJsonPath('payment.amount', '450.00')
            ->assertJsonPath('payment.discount', '50.00')
            ->assertJsonPath('payment.note', 'Day pass');

        $this->assertMatchesRegularExpression('/^OR-\d+-\d{6}$/', $response->json('payment.receipt_number'));
        $this->assertSame(1, DB::table('payments')->count());

        $this->actingAs($this->owner)->getJson($this->url('finance/income?type=walk_in'))
            ->assertOk()
            ->assertJsonCount(1, 'income')
            ->assertJsonPath('income.0.type', 'walk_in')
            ->assertJsonPath('income.0.amount', '450.00')
            ->assertJsonPath('income.0.member_name', null)
            ->assertJsonPath('income.0.payer_name', 'Walk-in guest');
    }

    public function test_a_merchandise_sale_lowers_stock_and_writes_one_movement_per_line(): void
    {
        $shirt = $this->openItem(['name' => 'Gym T-shirt', 'category' => 'merchandise', 'unit' => 'piece', 'sale_price' => '650.00'], 10);
        $bottle = $this->openItem(['name' => 'Gym bottle', 'category' => 'merchandise', 'unit' => 'piece', 'sale_price' => '380.00'], 5);

        $response = $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'card',
            'discount' => 100,
            'items' => [
                ['stock_item_id' => $shirt->id, 'quantity' => 1],
                ['stock_item_id' => $bottle->id, 'quantity' => 2],
            ],
        ])->assertCreated()
            ->assertJsonPath('payment.type', 'merchandise')
            ->assertJsonPath('payment.amount', '1310.00')
            ->assertJsonPath('payment.discount', '100.00')
            ->assertJsonPath('payment.method', 'card');

        $paymentId = $response->json('payment.id');
        $this->assertSame(9, $shirt->refresh()->quantity);
        $this->assertSame(3, $bottle->refresh()->quantity);

        $movements = StockMovement::withoutGlobalScope('gym')->where('payment_id', $paymentId)->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame([$shirt->id, $bottle->id], $movements->pluck('stock_item_id')->all());
        $this->assertSame([-1, -2], $movements->pluck('quantity')->all());
        $this->assertSame(['sale', 'sale'], $movements->map(fn (StockMovement $movement) => $movement->type->value)->all());
        $this->assertCountsMatch($shirt);
        $this->assertCountsMatch($bottle);
    }

    public function test_insufficient_stock_is_refused_and_nothing_is_written(): void
    {
        $bottle = $this->openItem(['name' => 'Gym bottle', 'category' => 'merchandise', 'unit' => 'piece', 'sale_price' => '380.00'], 5);

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $bottle->id, 'quantity' => 6]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(5, $bottle->refresh()->quantity);
    }

    public function test_only_active_merchandise_with_a_price_can_be_sold(): void
    {
        $gloves = $this->openItem(['name' => 'Gloves', 'category' => 'protective_gear', 'sale_price' => '1850.00'], 3);
        $shirt = $this->openItem(['name' => 'Gym T-shirt', 'category' => 'merchandise', 'unit' => 'piece', 'sale_price' => '650.00'], 4);

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $gloves->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->actingAs($this->owner)->postJson($this->url("stock-items/{$shirt->id}/archive"))->assertOk();

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $shirt->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertCountsMatch($gloves);
    }

    public function test_a_discount_cannot_exceed_the_total_and_paymongo_is_not_a_desk_method(): void
    {
        $shirt = $this->openItem(['name' => 'Gym T-shirt', 'category' => 'merchandise', 'unit' => 'piece', 'sale_price' => '650.00'], 4);

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'walk_in',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'amount' => 100,
            'discount' => 101,
        ])->assertUnprocessable()->assertJsonValidationErrors('discount');

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'discount' => 651,
            'items' => [['stock_item_id' => $shirt->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('discount');

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'walk_in',
            'payer_name' => 'Walk-in guest',
            'method' => 'paymongo',
            'amount' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(4, $shirt->refresh()->quantity);
    }

    public function test_a_sale_needs_finance_manage_and_a_buyer(): void
    {
        $viewer = $this->employeeWithRole(['finance.payments.view']);
        $desk = $this->employeeWithRole(['finance.payments.manage']);

        $payload = [
            'income_type' => 'walk_in',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'amount' => 500,
        ];

        $this->actingAs($viewer)->postJson($this->url('finance/sales'), $payload)->assertForbidden();
        $this->actingAs($desk)->postJson($this->url('finance/sales'), $payload)->assertCreated();

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'walk_in', 'method' => 'cash', 'amount' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors('payer_name');

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'membership', 'payer_name' => 'Guest', 'method' => 'cash', 'amount' => 500,
        ])->assertUnprocessable()->assertJsonValidationErrors('income_type');

        $this->assertSame(1, DB::table('payments')->count());
    }
}
