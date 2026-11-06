<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\GoodsReceipt;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\Procurement\Ordering;
use App\Support\Procurement\Payables;
use App\Support\Procurement\Receiving;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-09 on procurement: another gym's records are invisible and return 404. */
class ProcurementTenancyTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    public function test_another_gyms_supplier_request_order_receipt_and_payment_return_404(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->setUpProcurementGym();
        [$otherGym, $otherOwner, $supplier, $item, $request, $order, $receipt, $payment] = $this->otherGym();

        app(CurrentGym::class)->set($this->gym);

        $base = $this->url('');
        $this->actingAs($this->owner)->getJson($base.'suppliers')->assertOk()->assertJsonCount(1, 'suppliers');
        $this->assertNotContains($supplier->id, collect(
            $this->actingAs($this->owner)->getJson($base.'suppliers')->json('suppliers'),
        )->pluck('id'));

        $this->actingAs($this->owner)->putJson($base."suppliers/{$supplier->id}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->actingAs($this->owner)->getJson($base.'purchase-requests')->assertOk()->assertJsonCount(0, 'requests');
        $this->actingAs($this->owner)
            ->postJson($base."purchase-requests/{$request->id}/decision", ['decision' => 'approved'])
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->postJson($base."purchase-requests/{$request->id}/cancel", ['reason' => 'Wrong gym'])
            ->assertNotFound();

        $this->actingAs($this->owner)->getJson($base.'purchase-orders')->assertOk()->assertJsonCount(0, 'orders');
        $this->actingAs($this->owner)->getJson($base."purchase-orders/{$order->id}")->assertNotFound();
        $this->actingAs($this->owner)
            ->postJson($base."purchase-orders/{$order->id}/cancel", ['reason' => 'Wrong gym'])
            ->assertNotFound();
        $this->actingAs($this->owner)
            ->postJson($base."purchase-orders/{$order->id}/receipts", [
                'lines' => [['purchase_order_line_id' => $order->lines->sole()->id, 'quantity' => 1]],
            ])->assertNotFound();

        $this->actingAs($this->owner)->getJson($base."finance/payables/{$supplier->id}")->assertNotFound();
        $this->actingAs($this->owner)->postJson($base.'finance/supplier-payments', [
            'supplier_id' => $supplier->id, 'amount' => 10, 'method' => 'cash',
        ])->assertNotFound();

        $this->actingAs($this->owner)->getJson($base.'finance/payables')
            ->assertOk()
            ->assertJsonPath('payables.0.received', '0.00')
            ->assertJsonPath('payables.0.paid', '0.00')
            ->assertJsonPath('payables.0.outstanding', '0.00');

        // The other gym's records are untouched.
        app(CurrentGym::class)->set($otherGym);
        $this->assertCount(0, app(StockLedger::class)->mismatches());
        $this->assertSame(8, $item->refresh()->quantity);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame('360.00', $receipt->refresh()->total);
        $this->assertSame('100.00', $payment->refresh()->amount);
        $this->assertSame(1, $otherGym->expenses()->count());
    }

    /** @return array{Gym, User, Supplier, StockItem, PurchaseRequest, PurchaseOrder, GoodsReceipt, SupplierPayment} */
    private function otherGym(): array
    {
        $gym = Gym::factory()->approved()->create(['name' => 'Other Gym']);
        $owner = User::factory()->create(['first_name' => 'Oscar', 'last_name' => 'Other']);
        $gym->addPerson($owner, GymPersonType::Owner);
        app(CurrentGym::class)->set($gym);

        $supplier = $gym->suppliers()->create([
            'name' => 'Other Supplier', 'contact_person' => 'Pat', 'phone' => '0917 000 0000',
            'email' => 'other@supplier.test', 'address' => 'Elsewhere', 'payment_terms' => 'Cash',
            'notes' => null, 'status' => 'active',
        ]);

        $item = app(StockLedger::class)->open($gym, [
            'name' => 'Other gloves', 'sku' => 'OTH-1', 'category' => 'protective_gear',
            'unit' => 'pair', 'location' => 'Other cage', 'min_level' => 2, 'reorder_qty' => 5,
        ], 4, $owner);

        $request = $gym->purchaseRequests()->create([
            'number' => 'TMP-'.Str::upper(Str::random(16)),
            'status' => 'approved',
            'requested_by' => $owner->id,
            'decided_by' => null,
            'decided_at' => now(),
            'needed_by' => today()->addDays(5)->toDateString(),
            'note' => 'Other gym request.',
        ]);
        $request->forceFill(['number' => sprintf('PR-%d-%06d', $gym->id, $request->id)])->save();
        $request->lines()->create([
            'stock_item_id' => $item->id, 'quantity' => 4, 'est_unit_cost' => '100.00',
        ]);
        $request = $request->fresh(['lines.stockItem']);

        $order = app(Ordering::class)->fromRequest($gym, $request, $supplier, [[
            'purchase_request_line_id' => $request->lines->sole()->id,
            'quantity' => 4,
            'unit_cost' => '90.00',
        ]], $owner);

        [, $receipt] = app(Receiving::class)->receive(
            $gym,
            $order,
            [['purchase_order_line_id' => $order->lines->sole()->id, 'quantity' => 4]],
            $owner,
            today(),
            'Other gym delivery.',
        );

        $payment = app(Payables::class)->pay($supplier, '100.00', [
            'method' => 'cash', 'paid_on' => today()->toDateString(),
        ], $owner);

        return [$gym, $owner, $supplier, $item, $request, $order->refresh(), $receipt, $payment];
    }
}
