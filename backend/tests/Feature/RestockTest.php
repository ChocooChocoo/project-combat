<?php

namespace Tests\Feature;

use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-75: restock comes from minimum levels, open needs and stock already on order. */
class RestockTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
    }

    public function test_an_item_at_or_below_minimum_appears_and_an_item_above_does_not(): void
    {
        $above = $this->openItem(['name' => 'Skipping ropes', 'min_level' => 5, 'reorder_qty' => 10], 6);

        $response = $this->actingAs($this->owner)->getJson($this->url('restock'))->assertOk();

        $response->assertJsonCount(1, 'restock')
            ->assertJsonPath('restock.0.stock_item_id', $this->item->id)
            ->assertJsonPath('restock.0.name', 'Boxing gloves 14 oz')
            ->assertJsonPath('restock.0.quantity', 5)
            ->assertJsonPath('restock.0.min_level', 5)
            ->assertJsonPath('restock.0.on_order', 0)
            ->assertJsonPath('restock.0.open_needs', 0)
            ->assertJsonPath('restock.0.shortfall', 0)
            ->assertJsonPath('restock.0.suggested_quantity', 20);

        $this->assertNotContains($above->id, collect($response->json('restock'))->pluck('stock_item_id'));
    }

    public function test_open_needs_within_fourteen_days_or_without_a_date_add_to_the_shortfall(): void
    {
        $item = $this->openItem(['name' => 'Hand wraps 180 in', 'min_level' => 5, 'reorder_qty' => 4], 10);

        $this->gym->supplyNeeds()->create([
            'stock_item_id' => $item->id, 'needed_by' => today()->addDays(5)->toDateString(),
            'quantity' => 8, 'status' => 'open', 'created_by' => $this->owner->id,
        ]);
        $this->gym->supplyNeeds()->create([
            'stock_item_id' => $item->id, 'needed_by' => today()->addDays(20)->toDateString(),
            'quantity' => 50, 'status' => 'open', 'created_by' => $this->owner->id,
        ]);
        $this->gym->supplyNeeds()->create([
            'stock_item_id' => $item->id, 'needed_by' => today()->addDays(3)->toDateString(),
            'quantity' => 6, 'status' => 'requested', 'created_by' => $this->owner->id,
        ]);
        $this->gym->supplyNeeds()->create([
            'stock_item_id' => $item->id, 'needed_by' => null,
            'quantity' => 2, 'status' => 'open', 'created_by' => $this->owner->id,
        ]);

        $row = collect($this->actingAs($this->owner)->getJson($this->url('restock'))->assertOk()->json('restock'))
            ->firstWhere('stock_item_id', $item->id);

        $this->assertNotNull($row);
        $this->assertSame(10, $row['open_needs']);
        $this->assertSame(5, $row['shortfall']);
        $this->assertSame(5, $row['suggested_quantity']);
    }

    public function test_stock_on_order_subtracts_from_the_shortfall(): void
    {
        $item = $this->openItem(['name' => 'Focus mitts', 'min_level' => 5, 'reorder_qty' => 4], 10);

        $this->gym->supplyNeeds()->create([
            'stock_item_id' => $item->id, 'needed_by' => today()->addDays(2)->toDateString(),
            'quantity' => 10, 'status' => 'open', 'created_by' => $this->owner->id,
        ]);

        $before = collect($this->actingAs($this->owner)->getJson($this->url('restock'))->json('restock'))
            ->firstWhere('stock_item_id', $item->id);
        $this->assertSame(5, $before['shortfall']);

        $request = $this->approve($this->makeRequest([[
            'stock_item_id' => $item->id, 'quantity' => 10, 'est_unit_cost' => '100.00',
        ]]));
        $this->makeOrder($request, [[
            'purchase_request_line_id' => $request->lines->sole()->id,
            'quantity' => 5,
            'unit_cost' => '90.00',
        ]]);

        $rows = collect($this->actingAs($this->owner)->getJson($this->url('restock'))->assertOk()->json('restock'));

        $this->assertNull($rows->firstWhere('stock_item_id', $item->id));
    }

    public function test_suggested_quantity_uses_the_reorder_quantity_when_it_is_larger(): void
    {
        $item = $this->openItem(['name' => 'Mouth guards', 'min_level' => 5, 'reorder_qty' => 10], 1);

        $row = collect($this->actingAs($this->owner)->getJson($this->url('restock'))->assertOk()->json('restock'))
            ->firstWhere('stock_item_id', $item->id);

        $this->assertSame(4, $row['shortfall']);
        $this->assertSame(10, $row['suggested_quantity']);
    }

    public function test_archived_items_are_not_restocked(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $archived = $this->openItem(['name' => 'Old shin guards', 'min_level' => 4], 0);
        $archived->forceFill(['status' => 'archived'])->save();

        $rows = collect($this->actingAs($this->owner)->getJson($this->url('restock'))->assertOk()->json('restock'));

        $this->assertNull($rows->firstWhere('stock_item_id', $archived->id));
    }

    public function test_supply_needs_can_be_added_listed_and_deleted(): void
    {
        $created = $this->actingAs($this->requester)->postJson($this->url('supply-needs'), [
            'stock_item_id' => $this->item->id,
            'needed_by' => today()->addDays(3)->toDateString(),
            'quantity' => 6,
            'note' => 'For the Friday class.',
        ])->assertCreated()
            ->assertJsonPath('need.status', 'open')
            ->assertJsonPath('need.item_name', 'Boxing gloves 14 oz')
            ->assertJsonPath('need.quantity', 6)
            ->assertJsonPath('need.created_by_name', 'Rhea Santos')
            ->json('need');

        $this->actingAs($this->requester)->getJson($this->url('supply-needs'))
            ->assertOk()
            ->assertJsonCount(1, 'needs')
            ->assertJsonPath('needs.0.id', $created['id'])
            ->assertJsonPath('needs.0.needed_by', today()->addDays(3)->toDateString());

        $this->actingAs($this->requester)->deleteJson($this->url("supply-needs/{$created['id']}"))
            ->assertNoContent();

        $this->assertDatabaseMissing('supply_needs', ['id' => $created['id']]);
        $this->actingAs($this->requester)->getJson($this->url('supply-needs'))
            ->assertOk()
            ->assertJsonCount(0, 'needs');
    }
}
