<?php

namespace Tests\Feature;

use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

// (R-141)
class DecisionSupportSuggestionTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    private function item(array $attributes = []): StockItem
    {
        $item = StockItem::create(['name' => 'Gloves', 'category' => 'equipment', 'unit' => 'pair', 'min_level' => 5, 'reorder_qty' => 10, 'unit_cost' => '120.00', 'location' => 'Store']);
        DB::table('stock_items')->where('id', $item->id)->update(['quantity' => 1] + $attributes);

        return $item;
    }

    public function test_low_stock_suggestion_body_can_be_posted_as_is(): void
    {
        $item = $this->item();
        $response = $this->actingAs($this->owner)->getJson($this->decisionUrl('suggestions'))->assertOk()
            ->assertJsonPath('items.0.code', 'low_stock')
            ->assertJsonPath('items.0.action.path', 'purchase-requests')
            ->assertJsonPath('items.0.action.body.lines.0.stock_item_id', $item->id);
        $action = $response->json('items.0.action');
        $this->actingAs($this->owner)->postJson($this->url($action['path']), $action['body'])->assertCreated();
    }

    public function test_membership_ending_in_three_days_is_suggested(): void
    {
        $this->makeMembership($this->member, ['status' => 'active', 'ends_on' => today()->addDays(3)->toDateString()]);
        $this->actingAs($this->owner)->getJson($this->decisionUrl('suggestions'))->assertOk()
            ->assertJsonPath('items.0.code', 'expiring_memberships')
            ->assertJsonPath('items.0.action.type', 'link');
    }

    public function test_empty_gym_has_no_suggestions(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('suggestions'))->assertOk()->assertExactJson(['items' => []]);
    }

    public function test_role_without_decision_view_is_denied(): void
    {
        $employee = $this->employeeWithFinanceRole(['hr.staff.manage']);
        $this->actingAs($employee)->getJson($this->decisionUrl('suggestions'))->assertForbidden();
    }

    public function test_reading_suggestions_writes_nothing(): void
    {
        $this->item();
        $this->makeMembership($this->member, ['status' => 'active', 'ends_on' => today()->addDays(3)->toDateString()]);
        $before = [DB::table('purchase_requests')->count(), DB::table('announcements')->count()];
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->actingAs($this->owner)->getJson($this->decisionUrl('suggestions'))->assertOk();
        $this->assertSame([], $writes);
        $this->assertSame($before, [DB::table('purchase_requests')->count(), DB::table('announcements')->count()]);
    }
}
