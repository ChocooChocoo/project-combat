<?php

namespace Tests\Feature;

use App\Models\StockItem;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\StockSetup;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase, StockSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->setUpStockGym();
    }

    public function test_adding_an_item_records_the_opening_quantity_as_a_movement(): void
    {
        foreach ([12, 0] as $quantity) {
            $response = $this->actingAs($this->owner)->postJson($this->url('stock-items'), $this->itemData(['quantity' => $quantity]))
                ->assertCreated()->assertJsonPath('item.quantity', $quantity)->assertJsonPath('item.status', 'active');
            $item = StockItem::findOrFail($response->json('item.id'));
            $this->assertCount(1, $item->movements);
            $movement = $item->movements->sole();
            $this->assertSame('opening', $movement->type->value);
            $this->assertSame($quantity, $movement->quantity);
            $this->assertSame($quantity, $movement->balance_after);
            $this->assertSame($this->owner->id, $movement->actor_id);
            $this->assertSame('2026-11-05 10:00:00', $movement->occurred_at->format('Y-m-d H:i:s'));
            $this->assertCountsMatch($item);
        }
    }

    public function test_each_movement_type_moves_quantity_in_its_direction(): void
    {
        $ledger = app(StockLedger::class);
        $item = $this->openItem();
        $this->assertSame(10, $item->quantity);
        $this->assertCountsMatch($item);
        $issue = null;
        foreach ([['receipt', 5, 5, 15], ['issue', 4, -4, 11], ['return', 2, 2, 13],
            ['adjustment', 3, 3, 16], ['adjustment', -1, -1, 15], ['damaged', 2, -2, 13],
            ['lost', 1, -1, 12], ['sale', 2, -2, 10]] as [$type, $count, $signed, $balance]) {
            $extra = ['note' => '  Count checked  '];
            if ($type === 'return') {
                $extra['returns_movement_id'] = $issue->id;
            }
            $movement = $ledger->move($item, $type, $count, $this->owner, $extra);
            if ($type === 'issue') {
                $issue = $movement;
            }
            $this->assertSame($signed, $movement->quantity);
            $this->assertSame($balance, $movement->balance_after);
            $this->assertSame($balance, $item->quantity);
            $this->assertFalse($item->isDirty());
            $this->assertSame('Count checked', $movement->note);
            $this->assertCountsMatch($item);
        }
    }

    public function test_a_return_cannot_exceed_its_own_issue(): void
    {
        $ledger = app(StockLedger::class);
        $item = $this->openItem();
        $a = $ledger->move($item, 'issue', 3, $this->owner);
        $b = $ledger->move($item, 'issue', 2, $this->owner);
        $ledger->move($item, 'return', 3, $this->owner, ['returns_movement_id' => $a->id]);
        $this->assertCountsMatch($item);
        $this->assertValidationFails('quantity', fn () => $ledger->move($item, 'return', 1, $this->owner, ['returns_movement_id' => $a->id]));
        $this->assertSame(8, $item->refresh()->quantity);
        $ledger->move($item, 'return', 2, $this->owner, ['returns_movement_id' => $b->id]);
        $opening = $item->movements()->oldest('id')->firstOrFail();
        $this->assertValidationFails('returns_movement_id', fn () => $ledger->move($item, 'return', 1, $this->owner, ['returns_movement_id' => $opening->id]));
        $this->assertValidationFails('returns_movement_id', fn () => $ledger->move($item, 'return', 1, $this->owner));
        $this->assertSame(10, $item->refresh()->quantity);
        $this->assertSame(5, $item->movements()->count());
        $this->assertCountsMatch($item);
    }

    public function test_adjustment_damaged_and_lost_need_a_reason(): void
    {
        $item = $this->openItem();
        foreach (['adjustment', 'damaged', 'lost'] as $type) {
            foreach ([null, '   ', str_repeat('x', 256)] as $note) {
                $this->assertValidationFails('note', fn () => app(StockLedger::class)->move($item, $type, 1, $this->owner, ['note' => $note]));
            }
        }
        $this->actingAs($this->owner)->postJson($this->url("stock-items/{$item->id}/movements"), ['type' => 'adjustment', 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame(10, $item->refresh()->quantity);
        $this->assertCountsMatch($item);
    }

    public function test_stock_never_goes_negative(): void
    {
        $item = $this->openItem([], 3);
        $ledger = app(StockLedger::class);
        $this->assertValidationFails('quantity', fn () => $ledger->move($item, 'issue', 4, $this->owner));
        $this->assertValidationFails('quantity', fn () => $ledger->move($item, 'adjustment', -4, $this->owner, ['note' => 'Count']));
        $this->actingAs($this->owner)->postJson($this->url("stock-items/{$item->id}/movements"), ['type' => 'lost', 'quantity' => 4, 'note' => 'Missing'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(3, $item->refresh()->quantity);
        $this->assertSame(1, $item->movements()->count());
        $this->assertCountsMatch($item);
    }

    public function test_a_direct_quantity_write_is_refused(): void
    {
        $item = $this->openItem();
        $this->assertLogicFails(function () use ($item) {
            $item->quantity = 99;
            $item->save();
        });
        $this->assertLogicFails(fn () => $item->refresh()->forceFill(['quantity' => 5])->save());
        $this->assertLogicFails(fn () => $item->refresh()->increment('quantity'));
        $this->assertLogicFails(fn () => (new StockItem($this->itemData()))->forceFill(['quantity' => 4])->save());
        $this->actingAs($this->owner)->putJson($this->url("stock-items/{$item->id}"), $this->itemData(['quantity' => 500]))
            ->assertOk()->assertJsonPath('item.quantity', 10);
        $movement = $item->movements()->firstOrFail();
        $this->assertLogicFails(fn () => $movement->forceFill(['quantity' => 99])->save());
        $this->assertLogicFails(fn () => $movement->refresh()->delete());
        $this->assertSame(10, $item->refresh()->quantity);
        $this->assertCountsMatch($item);
    }

    public function test_archived_items_reject_movements(): void
    {
        $item = $this->openItem();
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->owner)->postJson($this->url("stock-items/{$item->id}/archive"))
                ->assertOk()->assertJsonPath('item.status', 'archived');
        }
        $this->assertValidationFails('item', fn () => app(StockLedger::class)->move($item, 'receipt', 1, $this->owner));
        $this->postJson($this->url("stock-items/{$item->id}/movements"), ['type' => 'receipt', 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->putJson($this->url("stock-items/{$item->id}"), $this->itemData())->assertStatus(409);
        $this->getJson($this->url('stock-items'))->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('summary.total', 0);
        $this->getJson($this->url('stock-items?archived=1'))->assertOk()->assertJsonCount(1, 'items');
        $this->assertCountsMatch($item);
    }

    public function test_movements_record_who_and_when_and_history_lists_them(): void
    {
        $item = $this->openItem();
        $path = $this->url("stock-items/{$item->id}/movements");
        $response = $this->actingAs($this->owner)->postJson($path, [
            'type' => 'issue', 'quantity' => 2, 'issued_to_user_id' => $this->employee->id,
            'occurred_at' => '2000-01-01 00:00:00',
        ])->assertCreated()->assertJsonPath('movement.actor_name', 'Owen Reyes')
            ->assertJsonPath('movement.issued_to_name', 'Ella Santos')
            ->assertJsonPath('movement.occurred_at', '2026-11-05 10:00:00')
            ->assertJsonPath('movement.returned', 0)->assertJsonPath('movement.outstanding', 2);
        $this->getJson($path)->assertOk()->assertJsonCount(2, 'movements')
            ->assertJsonPath('movements.0.id', $response->json('movement.id'))->assertJsonPath('movements.1.type', 'opening')
            ->assertJsonPath('open_issues.0.outstanding', 2)->assertJsonPath('meta.per_page', 30);
        foreach (['sale', 'opening'] as $type) {
            $this->postJson($path, ['type' => $type, 'quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('type');
        }
        $this->postJson($path, ['type' => 'issue', 'quantity' => 1, 'returns_movement_id' => $response->json('movement.id')])
            ->assertUnprocessable()->assertJsonValidationErrors('returns_movement_id');
        $people = $this->getJson($this->url('stock-items/recipients'))->assertOk()->json('people');
        $this->assertSame(['Ella Santos', 'Owen Reyes'], array_column($people, 'name'));
        $this->assertCountsMatch($item);
    }

    public function test_permissions_gate_each_action(): void
    {
        $item = $this->openItem();
        $viewer = $this->employeeWithRole(['inventory.items.view']);
        $desk = $this->employeeWithRole(['inventory.items.view', 'inventory.movements.manage']);
        $this->actingAs($viewer);
        foreach (['stock-items', "stock-items/{$item->id}", "stock-items/{$item->id}/movements"] as $path) {
            $this->getJson($this->url($path))->assertOk();
        }
        $this->postJson($this->url('stock-items'), $this->itemData(['quantity' => 1]))->assertForbidden();
        $this->putJson($this->url("stock-items/{$item->id}"), $this->itemData())->assertForbidden();
        $this->postJson($this->url("stock-items/{$item->id}/archive"))->assertForbidden();
        $this->postJson($this->url("stock-items/{$item->id}/movements"), ['type' => 'receipt', 'quantity' => 1])->assertForbidden();
        $this->getJson($this->url('stock-items/recipients'))->assertForbidden();
        $this->actingAs($desk)->postJson($this->url("stock-items/{$item->id}/movements"), ['type' => 'receipt', 'quantity' => 1])->assertCreated();
        $this->putJson($this->url("stock-items/{$item->id}"), $this->itemData())->assertForbidden();
        $this->actingAs($this->employee)->getJson($this->url('stock-items'))->assertForbidden();
        $this->assertCountsMatch($item);
    }

    public function test_the_stock_count_report_finds_a_mismatch(): void
    {
        $item = $this->openItem();
        $this->artisan('app:check-stock-counts')->expectsOutputToContain('Every stock count matches its movements.')->assertExitCode(0);
        DB::table('stock_items')->where('id', $item->id)->update(['quantity' => 99]);
        $this->artisan('app:check-stock-counts')->expectsOutputToContain($item->name)->assertExitCode(1);
        $this->assertLogicFails(fn () => app(StockLedger::class)->assertQuantityMatchesMovements($item));
        $this->assertSame(99, $item->refresh()->quantity);
    }
}
