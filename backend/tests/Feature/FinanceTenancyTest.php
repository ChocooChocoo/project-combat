<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-09: one gym's money never appears in another gym. */
class FinanceTenancyTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    private Gym $otherGym;

    private User $otherOwner;

    private User $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();

        $this->otherGym = Gym::factory()->approved()->create();
        $this->otherOwner = User::factory()->create(['first_name' => 'Olga', 'last_name' => 'Bautista']);
        $this->otherMember = User::factory()->create(['first_name' => 'Nico', 'last_name' => 'Flores']);
        $this->otherGym->addPerson($this->otherOwner, GymPersonType::Owner);
        $this->otherGym->addPerson($this->otherMember, GymPersonType::Member);
    }

    public function test_income_and_receivables_are_limited_to_their_gym(): void
    {
        $membership = $this->makeMembership($this->member, ['amount_charged' => 1000]);
        $this->payMembership($membership, '400.00');

        $otherMembership = $this->membershipIn($this->otherGym, $this->otherMember, '1000.00');
        $this->paymentIn($this->otherGym, $otherMembership, '700.00');

        $mine = $this->actingAs($this->owner)->getJson($this->url('finance/income'))->assertOk();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/income")->assertOk();

        $this->assertSame(1, $mine->json('meta.total'));
        $this->assertSame(400.0, (float) $mine->json('income.0.amount'));
        $this->assertSame(1, $theirs->json('meta.total'));
        $this->assertSame(700.0, (float) $theirs->json('income.0.amount'));

        $this->actingAs($this->owner)->getJson("/api/gyms/{$this->otherGym->id}/finance/income")->assertNotFound();
        $this->actingAs($this->otherOwner)->getJson($this->url('finance/income'))->assertNotFound();

        $mineReceivables = $this->actingAs($this->owner)->getJson($this->url('finance/receivables'))->assertOk();
        $theirReceivables = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/receivables")->assertOk();

        $this->assertSame([$this->member->id], collect($mineReceivables->json('receivables'))->pluck('user_id')->all());
        $this->assertSame([$this->otherMember->id], collect($theirReceivables->json('receivables'))->pluck('user_id')->all());
        $this->actingAs($this->owner)->getJson("/api/gyms/{$this->otherGym->id}/finance/receivables")->assertNotFound();
    }

    public function test_expenses_and_their_corrections_are_limited_to_their_gym(): void
    {
        $mine = $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'rent', 'amount' => 1000, 'incurred_on' => '2026-11-01',
        ])->assertCreated()->json('expense.id');

        $theirs = $this->actingAs($this->otherOwner)->postJson("/api/gyms/{$this->otherGym->id}/finance/expenses", [
            'category' => 'utilities', 'amount' => 700, 'incurred_on' => '2026-11-02',
        ])->assertCreated()->json('expense.id');

        $mineList = $this->actingAs($this->owner)->getJson($this->url('finance/expenses'))->assertOk();
        $theirList = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/expenses")->assertOk();

        $this->assertSame([$mine], collect($mineList->json('expenses'))->pluck('id')->all());
        $this->assertSame([$theirs], collect($theirList->json('expenses'))->pluck('id')->all());

        $this->actingAs($this->otherOwner)
            ->postJson("/api/gyms/{$this->otherGym->id}/finance/expenses/{$mine}/correct", ['reason' => 'Wrong gym'])
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->postJson($this->url("finance/expenses/{$theirs}/correct"), ['reason' => 'Wrong gym'])
            ->assertNotFound();

        $this->assertSame(0, DB::table('expenses')->where('corrects_id', $mine)->count());
        $this->assertSame(0, DB::table('expenses')->where('corrects_id', $theirs)->count());
    }

    public function test_a_sale_cannot_use_another_gyms_stock(): void
    {
        $mine = $this->stockItemIn($this->gym, $this->owner, 'Gym T-shirt');
        $theirs = $this->stockItemIn($this->otherGym, $this->otherOwner, 'Other T-shirt');

        $this->actingAs($this->otherOwner)->postJson("/api/gyms/{$this->otherGym->id}/finance/sales", [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $mine->id, 'quantity' => 1]],
        ])->assertNotFound();

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $theirs->id, 'quantity' => 1]],
        ])->assertNotFound();

        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(10, $mine->refresh()->quantity);
        $this->assertSame(10, $theirs->refresh()->quantity);

        $this->actingAs($this->owner)->postJson($this->url('finance/sales'), [
            'income_type' => 'merchandise',
            'payer_name' => 'Walk-in guest',
            'method' => 'cash',
            'items' => [['stock_item_id' => $mine->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(9, $mine->refresh()->quantity);
        $this->assertSame(10, $theirs->refresh()->quantity);
        $this->assertSame(1, DB::table('payments')->where('gym_id', $this->gym->id)->count());
        $this->assertSame(0, DB::table('payments')->where('gym_id', $this->otherGym->id)->count());
    }

    private function membershipIn(Gym $gym, User $user, string $amount): Membership
    {
        app(CurrentGym::class)->set($gym);
        $plan = MembershipPlan::factory()->forGym($gym)->create([
            'published_at' => now(), 'total_fee' => $amount, 'billing_type' => 'time', 'duration_months' => 1,
        ]);

        $membership = new Membership;
        $membership->fill([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'emergency_contacts' => [['name' => 'Contact', 'relationship' => 'Parent', 'phone' => '0917 000 0000']],
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'waivers' => array_keys(config('membership.waivers')),
            'signature_name' => trim("{$user->first_name} {$user->last_name}"),
            'signed_on' => today()->toDateString(),
        ]);
        $membership->forceFill(['status' => 'active', 'amount_charged' => $amount])->save();

        return $membership->refresh();
    }

    private function paymentIn(Gym $gym, Membership $membership, string $amount): Payment
    {
        app(CurrentGym::class)->set($gym);

        return Payment::create([
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'amount' => $amount,
            'method' => 'cash',
            'paid_at' => now(),
        ]);
    }

    private function stockItemIn(Gym $gym, User $actor, string $name): StockItem
    {
        app(CurrentGym::class)->set($gym);

        return app(StockLedger::class)->open($gym, [
            'name' => $name, 'category' => 'merchandise', 'unit' => 'piece',
            'location' => 'Front desk', 'sale_price' => '650.00',
        ], 10, $actor);
    }
}
