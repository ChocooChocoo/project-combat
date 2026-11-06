<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\StockSetup;
use Tests\TestCase;

class StockTenancyTest extends TestCase
{
    use RefreshDatabase, StockSetup;

    public function test_another_gym_cannot_touch_stock(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:00:00'));
        $this->setUpStockGym();
        $item = $this->openItem();
        $issue = app(StockLedger::class)->move($item, 'issue', 2, $this->owner, ['issued_to_user_id' => $this->employee->id]);
        $other = Gym::factory()->approved()->create();
        $otherOwner = User::factory()->create();
        $other->addPerson($otherOwner, GymPersonType::Owner);
        $otherItem = $this->openItemFor($other, $otherOwner, ['name' => 'Other gloves']);
        $base = "/api/gyms/{$other->id}/stock-items";
        $this->actingAs($otherOwner)->getJson("{$base}/{$item->id}")->assertNotFound();
        $this->putJson("{$base}/{$item->id}", $this->itemData())->assertNotFound();
        $this->postJson("{$base}/{$item->id}/archive")->assertNotFound();
        $this->getJson("{$base}/{$item->id}/movements")->assertNotFound();
        $this->postJson("{$base}/{$item->id}/movements", ['type' => 'receipt', 'quantity' => 1])->assertNotFound();
        $this->postJson("{$base}/{$otherItem->id}/movements", ['type' => 'return', 'quantity' => 1, 'returns_movement_id' => $issue->id])->assertNotFound();
        $this->postJson("{$base}/{$otherItem->id}/movements", ['type' => 'issue', 'quantity' => 1, 'issued_to_user_id' => $this->employee->id])->assertNotFound();
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $otherItem->id);
        $this->getJson($this->url('stock-items'))->assertNotFound();
        app(CurrentGym::class)->set($this->gym);
        $this->assertSame(8, $item->refresh()->quantity);
        $this->assertSame(2, $item->movements()->count());
        $this->assertCountsMatch($item);
        $this->assertCountsMatch($otherItem);
    }
}
