<?php

namespace Tests\Feature;

use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportStockTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_current_active_stock_at_minimum_and_mixed_units_are_not_summed(): void
    {
        foreach ([[5, 5, 'pair', 'active'], [0, null, 'litre', 'active'], [2, 3, 'piece', 'active'], [0, 5, 'box', 'archived']] as $index => [$quantity, $min, $unit, $status]) {
            $item = StockItem::create(['name' => 'Item '.$index, 'category' => 'equipment', 'unit' => $unit, 'min_level' => $min, 'location' => 'Store']);
            DB::table('stock_items')->where('id', $item->id)->update(['quantity' => $quantity, 'status' => $status]);
        }
        foreach (['2020-01-01', '2026-11-07'] as $day) {
            $this->actingAs($this->owner)->getJson($this->decisionUrl('stock')."?from={$day}&to={$day}")->assertOk()
                ->assertJsonPath('as_of', '2026-11-07')->assertJsonPath('summary.items', 3)
                ->assertJsonPath('summary.low_items', 2)->assertJsonPath('summary.out_items', 1)
                ->assertJsonPath('items.0.low', true)->assertJsonPath('items.1.low', false)->assertJsonCount(3, 'items')
                ->assertJsonMissingPath('summary.total_quantity');
        }
    }

    public function test_purchasing_creation_cohort_keeps_current_status_and_receipts_use_own_date(): void
    {
        $supplier = $this->gym->suppliers()->create(['name' => 'Fixture supplier']);
        $oldOrder = DB::table('purchase_orders')->insertGetId(['gym_id' => $this->gym->id, 'number' => 'PO-OLD',
            'supplier_id' => $supplier->id, 'ordered_by' => $this->owner->id, 'total' => '123.45', 'status' => 'received',
            'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00']);
        foreach (['2026-11-01 00:00:00', '2026-11-07 23:59:59.999999', '2026-11-08 00:00:00'] as $index => $at) {
            DB::table('purchase_requests')->insert(['gym_id' => $this->gym->id, 'number' => 'PR-'.$index, 'status' => $index === 1 ? 'cancelled' : 'pending', 'requested_by' => $this->owner->id, 'created_at' => $at, 'updated_at' => $at]);
        }
        foreach (['partly_received', 'completed_short', 'cancelled'] as $index => $status) {
            DB::table('purchase_orders')->insert(['gym_id' => $this->gym->id, 'number' => 'PO-'.$index,
                'supplier_id' => $supplier->id, 'ordered_by' => $this->owner->id, 'total' => '500.00', 'status' => $status,
                'created_at' => '2026-11-07 23:59:59', 'updated_at' => '2026-11-07 23:59:59']);
        }
        foreach (['2026-11-07', '2026-11-08'] as $index => $day) {
            DB::table('goods_receipts')->insert(['gym_id' => $this->gym->id, 'number' => 'GR-'.$index,
                'purchase_order_id' => $oldOrder, 'received_by' => $this->owner->id, 'received_on' => $day, 'total' => '123.45']);
        }
        $before = DB::table('purchase_orders')->orderBy('id')->get()->toArray();
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('stock').'?from=2026-11-01&to=2026-11-07')->assertOk()
            ->assertJsonPath('purchasing.basis', 'created_in_range_current_status')->assertJsonCount(2, 'purchasing.requests')
            ->assertJsonCount(3, 'purchasing.orders')->assertJsonPath('purchasing.receipts.count', 1)->assertJsonPath('purchasing.receipts.total', '123.45');
        $this->assertSame(['cancelled', 'completed_short', 'partly_received'], array_column($response->json('purchasing.orders'), 'status'));
        $this->assertEquals($before, DB::table('purchase_orders')->orderBy('id')->get()->toArray());
        $this->getJson($this->decisionUrl('stock').'?from=2026-11-07&to=2026-11-07')->assertOk()->assertJsonPath('purchasing.receipts.count', 1);
    }

    public function test_empty_snapshot_and_day_have_decimal_zero_receipts(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('stock').'?from=2026-11-07&to=2026-11-07')->assertOk()
            ->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.items', 0)->assertJsonPath('purchasing.receipts.total', '0.00');
    }
}
