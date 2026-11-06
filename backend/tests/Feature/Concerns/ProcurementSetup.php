<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\Procurement\Ordering;
use App\Support\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Shared fixtures for the Packet 6E procurement tests. */
trait ProcurementSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $approver;

    protected User $requester;

    protected Supplier $supplier;

    protected StockItem $item;

    protected function setUpProcurementGym(): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->approver = User::factory()->create(['first_name' => 'Ava', 'last_name' => 'Cruz']);
        $this->requester = User::factory()->create(['first_name' => 'Rhea', 'last_name' => 'Santos']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->approver, GymPersonType::Employee);
        $this->gym->addPerson($this->requester, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);

        $this->giveProcurementRole($this->approver, [
            'procurement.requests.view', 'procurement.requests.manage', 'procurement.requests.approve',
            'procurement.suppliers.view', 'procurement.suppliers.manage',
            'procurement.orders.view', 'procurement.orders.manage', 'procurement.receipts.manage',
            'finance.payables.view', 'finance.payables.manage',
            'inventory.items.view', 'inventory.items.manage',
        ], 'Purchasing approver');

        $this->giveProcurementRole($this->requester, [
            'procurement.requests.view', 'procurement.requests.manage', 'procurement.requests.approve',
            'procurement.suppliers.view', 'procurement.orders.view', 'procurement.orders.manage',
            'finance.payables.view', 'inventory.items.view',
        ], 'Purchasing requester');

        $this->supplier = $this->gym->suppliers()->create([
            'name' => 'Manila Fight Gear Supply',
            'contact_person' => 'Cesar Lim',
            'phone' => '0917 555 0301',
            'email' => 'orders@manilafightgear.test',
            'address' => '18 Quezon Avenue',
            'payment_terms' => 'Net 15',
            'notes' => 'Gloves and protective gear.',
            'status' => 'active',
        ]);

        $this->item = app(StockLedger::class)->open($this->gym, [
            'name' => 'Boxing gloves 14 oz',
            'sku' => 'GLV-14',
            'category' => 'protective_gear',
            'unit' => 'pair',
            'location' => 'Equipment cage',
            'min_level' => 5,
            'reorder_qty' => 20,
            'unit_cost' => '1250.00',
        ], 5, $this->owner);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function giveProcurementRole(User $user, array $permissions, string $name = 'Purchasing role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name.' '.$user->id)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }

    /** @param array<int, array<string, mixed>>|null $lines */
    protected function makeRequest(
        ?array $lines = null,
        ?User $requester = null,
        string $status = 'pending',
        ?string $neededBy = null,
    ): PurchaseRequest {
        app(CurrentGym::class)->set($this->gym);
        $requester ??= $this->requester;
        $lines ??= [[
            'stock_item_id' => $this->item->id,
            'quantity' => 10,
            'est_unit_cost' => '100.00',
        ]];

        $request = $this->gym->purchaseRequests()->create([
            'number' => 'TMP-'.Str::upper(Str::random(16)),
            'status' => 'pending',
            'requested_by' => $requester->id,
            'needed_by' => $neededBy,
            'note' => 'Restock for the coming classes.',
        ]);
        $request->forceFill(['number' => sprintf('PR-%d-%06d', $this->gym->id, $request->id)])->save();

        foreach ($lines as $line) {
            $request->lines()->create([
                'stock_item_id' => $line['stock_item_id'] ?? $this->item->id,
                'quantity' => $line['quantity'] ?? 10,
                'est_unit_cost' => $line['est_unit_cost'] ?? null,
                'supply_need_id' => $line['supply_need_id'] ?? null,
            ]);
        }

        if ($status !== 'pending') {
            $request->forceFill([
                'status' => $status,
                'decided_by' => $status === 'approved' ? $this->owner->id : null,
                'decided_at' => $status === 'approved' ? now() : null,
                'decision_reason' => $status === 'rejected' ? 'Not this month.' : null,
            ])->save();
        }

        return $request->fresh(['lines.stockItem', 'requestedBy', 'decidedBy']);
    }

    protected function approve(PurchaseRequest $request): PurchaseRequest
    {
        $request->forceFill([
            'status' => 'approved',
            'decided_by' => $this->owner->id,
            'decided_at' => now(),
        ])->save();

        return $request->fresh(['lines.stockItem', 'requestedBy', 'decidedBy']);
    }

    protected function openItem(array $overrides = [], int $quantity = 10): StockItem
    {
        app(CurrentGym::class)->set($this->gym);

        return app(StockLedger::class)->open($this->gym, array_replace([
            'name' => 'Hand wraps 180 in',
            'category' => 'protective_gear',
            'unit' => 'pair',
            'location' => 'Front desk cabinet',
        ], $overrides), $quantity, $this->owner);
    }

    protected function assertCountsMatch(StockItem $item): void
    {
        app(StockLedger::class)->assertQuantityMatchesMovements($item);
        $this->addToAssertionCount(1);
    }

    protected function makeOrder(PurchaseRequest $request, ?array $lines = null): PurchaseOrder
    {
        $lines ??= $request->lines->map(fn ($line) => [
            'purchase_request_line_id' => $line->id,
            'quantity' => $line->quantity,
            'unit_cost' => '90.00',
        ])->all();

        return app(Ordering::class)->fromRequest(
            $this->gym,
            $request,
            $this->supplier,
            $lines,
            $this->owner,
            Carbon::parse('2026-11-20'),
        );
    }

    protected function supplierData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Quezon Sports Medical Trading',
            'contact_person' => 'Nina Perez',
            'phone' => '0917 555 0302',
            'email' => 'sales@quezonmedical.test',
            'address' => '7 Banawe Street',
            'payment_terms' => 'Net 30',
            'notes' => 'First aid supplies.',
            'status' => 'active',
        ], $overrides);
    }
}
