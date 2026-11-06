<?php

namespace Tests\Feature;

use App\Enums\StockItemStatus;
use App\Models\User;
use App\Notifications\StockLow;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\StockSetup;
use Tests\TestCase;

class LowStockTest extends TestCase
{
    use RefreshDatabase, StockSetup;

    protected User $manager;

    protected User $desk;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->setUpStockGym();
        $this->manager = $this->employeeWithRole(['inventory.items.view', 'inventory.items.manage']);
        $this->desk = $this->employeeWithRole(['inventory.items.view', 'inventory.movements.manage']);
    }

    public function test_a_drop_to_the_minimum_sends_one_notice(): void
    {
        $item = $this->openItem(['min_level' => 5]);
        $ledger = app(StockLedger::class);
        $ledger->move($item, 'issue', 4, $this->owner);
        Notification::assertNothingSent();
        $ledger->move($item, 'issue', 1, $this->owner);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        Notification::assertSentToTimes($this->manager, StockLow::class, 1);
        Notification::assertNotSentTo($this->desk, StockLow::class);
        $this->assertNotNull($item->refresh()->low_notified_at);
        $this->assertCountsMatch($item);
    }

    public function test_no_repeat_while_stock_stays_low(): void
    {
        $item = $this->openItem(['min_level' => 5]);
        $ledger = app(StockLedger::class);
        $ledger->move($item, 'issue', 5, $this->owner);
        $ledger->move($item, 'issue', 1, $this->owner);
        $ledger->move($item, 'adjustment', -1, $this->owner, ['note' => 'Count check']);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        Notification::assertSentToTimes($this->manager, StockLow::class, 1);
        Notification::assertNotSentTo($this->desk, StockLow::class);
        $this->assertCountsMatch($item);
    }

    public function test_the_flag_clears_above_the_minimum_and_a_new_drop_notifies_again(): void
    {
        $item = $this->openItem(['min_level' => 5]);
        $ledger = app(StockLedger::class);
        $ledger->move($item, 'issue', 5, $this->owner);
        $ledger->move($item, 'receipt', 1, $this->owner);
        $this->assertNull($item->refresh()->low_notified_at);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        $ledger->move($item, 'issue', 1, $this->owner);
        Notification::assertSentToTimes($this->owner, StockLow::class, 2);
        Notification::assertSentToTimes($this->manager, StockLow::class, 2);
        $this->assertNotNull($item->refresh()->low_notified_at);
        $this->assertCountsMatch($item);
    }

    public function test_changing_the_minimum_runs_the_check(): void
    {
        $item = $this->openItem([], 6);
        Notification::assertNothingSent();
        $this->actingAs($this->owner)->putJson($this->url("stock-items/{$item->id}"), $this->itemData(['min_level' => 8]))
            ->assertOk()->assertJsonPath('item.is_low', true);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        $this->assertNotNull($item->refresh()->low_notified_at);
        $this->putJson($this->url("stock-items/{$item->id}"), $this->itemData(['min_level' => null]))->assertOk();
        $this->assertNull($item->refresh()->low_notified_at);
        $this->putJson($this->url("stock-items/{$item->id}"), $this->itemData(['name' => 'Renamed gloves']))->assertOk();
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        Notification::assertSentToTimes($this->manager, StockLow::class, 1);
        $this->assertCountsMatch($item);
    }

    public function test_the_owner_receives_item_quantity_minimum_and_location(): void
    {
        $item = $this->openItem(['min_level' => 5], 4);
        Notification::assertSentTo($this->owner, StockLow::class, function (StockLow $notice) use ($item) {
            $this->assertSame(['database'], $notice->via($this->owner));
            $this->assertSame([
                'title' => 'Low stock',
                'message' => 'Boxing gloves is down to 4 pairs (minimum 5) at Equipment cage.',
                'gym_id' => $this->gym->id, 'stock_item_id' => $item->id,
                'item_name' => 'Boxing gloves', 'quantity' => 4, 'min_level' => 5, 'location' => 'Equipment cage',
            ], $notice->toArray($this->owner));

            return true;
        });
        app(StockLedger::class)->checkLowStock($item);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        $archived = $this->openItem(['name' => 'Archived gloves', 'min_level' => 5]);
        $this->actingAs($this->owner)->postJson($this->url("stock-items/{$archived->id}/archive"))->assertOk();
        $archived->refresh()->forceFill(['min_level' => 20])->save();
        $this->assertSame(StockItemStatus::Archived, $archived->status);
        app(StockLedger::class)->checkLowStock($archived);
        $this->assertNull($archived->refresh()->low_notified_at);
        $this->assertValidationFails('item', fn () => app(StockLedger::class)->move($archived, 'issue', 1, $this->owner));
        $this->putJson($this->url("stock-items/{$archived->id}"), $this->itemData())->assertStatus(409);
        Notification::assertSentToTimes($this->owner, StockLow::class, 1);
        Notification::assertSentToTimes($this->manager, StockLow::class, 1);
        Notification::assertNotSentTo($this->desk, StockLow::class);
        $this->assertCountsMatch($item);
        $this->assertCountsMatch($archived);
    }
}
