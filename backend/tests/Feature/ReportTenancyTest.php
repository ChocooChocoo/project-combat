<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-09 and R-68: one gym's money never appears in another gym's reports. */
class ReportTenancyTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    private Gym $otherGym;

    private User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();

        $this->otherGym = Gym::factory()->approved()->create();
        $this->otherOwner = User::factory()->create(['first_name' => 'Olga', 'last_name' => 'Santos']);
        $this->otherGym->addPerson($this->otherOwner, GymPersonType::Owner);

        $this->seedReportData($this->gym, $this->owner, [
            'income' => '400.00',
            'expense' => '100.00',
            'supplier_payment' => '50.00',
            'payroll_net' => '1000.00',
        ]);

        $this->seedReportData($this->otherGym, $this->otherOwner, [
            'income' => '700.00',
            'expense' => '200.00',
            'supplier_payment' => '70.00',
            'payroll_net' => '2000.00',
        ]);

        app(CurrentGym::class)->set($this->gym);
    }

    public function test_each_report_shows_only_its_own_gyms_income_expenses_payments_and_payroll(): void
    {
        $range = '?from=2026-11-01&to=2026-11-30';

        $mine = $this->actingAs($this->owner)
            ->getJson($this->url("finance/reports/cash-flow{$range}"))->assertOk()->json();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/reports/cash-flow{$range}")->assertOk()->json();

        $this->assertSame('400.00', $mine['totals']['in']);
        $this->assertSame('150.00', $mine['totals']['out']);
        $this->assertSame('250.00', $mine['totals']['net']);
        $this->assertSame('700.00', $theirs['totals']['in']);
        $this->assertSame('270.00', $theirs['totals']['out']);
        $this->assertSame('430.00', $theirs['totals']['net']);

        $mine = $this->actingAs($this->owner)
            ->getJson($this->url("finance/reports/income-expense{$range}"))->assertOk()->json();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/reports/income-expense{$range}")->assertOk()->json();

        $this->assertSame('400.00', $mine['totals']['income']);
        $this->assertSame('100.00', $mine['totals']['expenses']);
        $this->assertSame('700.00', $theirs['totals']['income']);
        $this->assertSame('200.00', $theirs['totals']['expenses']);

        $mine = $this->actingAs($this->owner)
            ->getJson($this->url("finance/reports/payroll{$range}"))->assertOk()->json();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/reports/payroll{$range}")->assertOk()->json();

        $this->assertSame('1000.00', $mine['totals']['net']);
        $this->assertSame(1, $mine['totals']['count']);
        $this->assertSame('2000.00', $theirs['totals']['net']);
        $this->assertSame(1, $theirs['totals']['count']);

        $mine = $this->actingAs($this->owner)
            ->getJson($this->url("finance/reports/payouts{$range}"))->assertOk()->json();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/finance/reports/payouts{$range}")->assertOk()->json();

        $this->assertSame('1000.00', $mine['totals']['payroll_net']);
        $this->assertSame('50.00', $mine['totals']['supplier_payments']);
        $this->assertSame('1050.00', $mine['totals']['total']);
        $this->assertSame('2000.00', $theirs['totals']['payroll_net']);
        $this->assertSame('70.00', $theirs['totals']['supplier_payments']);
        $this->assertSame('2070.00', $theirs['totals']['total']);
    }

    public function test_a_foreign_gym_cannot_be_read_through_the_report_routes(): void
    {
        $range = '?from=2026-11-01&to=2026-11-30';

        foreach ([
            'finance/reports/cash-flow',
            'finance/reports/income-expense',
            'finance/reports/payroll',
            'finance/reports/payouts',
        ] as $path) {
            $this->actingAs($this->owner)
                ->getJson("/api/gyms/{$this->otherGym->id}/{$path}{$range}")
                ->assertNotFound();

            $this->actingAs($this->otherOwner)
                ->getJson($this->url("{$path}{$range}"))
                ->assertNotFound();
        }
    }

    /** @param array{income: string, expense: string, supplier_payment: string, payroll_net: string} $amounts */
    private function seedReportData(Gym $gym, User $owner, array $amounts): void
    {
        app(CurrentGym::class)->set($gym);

        $gym->payments()->create([
            'user_id' => $owner->id,
            'amount' => $amounts['income'],
            'method' => 'cash',
            'paid_at' => '2026-11-02 10:00:00',
            'income_type' => 'membership',
        ]);

        $gym->expenses()->create([
            'category' => 'rent',
            'amount' => $amounts['expense'],
            'incurred_on' => '2026-11-03',
            'recorded_by' => $owner->id,
        ]);

        $supplier = $gym->suppliers()->create(['name' => "Supplier {$gym->id}"]);

        $gym->supplierPayments()->create([
            'supplier_id' => $supplier->id,
            'amount' => $amounts['supplier_payment'],
            'method' => 'bank',
            'paid_on' => '2026-11-05',
            'recorded_by' => $owner->id,
        ]);

        $run = $gym->payrollRuns()->create([
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-15',
            'status' => 'released',
            'steps' => ['owner'],
            'current_step' => 0,
            'round' => 1,
            'prepared_by' => $owner->id,
            'released_by' => $owner->id,
            'released_at' => '2026-11-16 10:00:00',
        ]);

        $run->lines()->create([
            'user_id' => $owner->id,
            'name' => "Owner {$gym->id}",
            'gross_pay' => $amounts['payroll_net'],
            'net_pay' => $amounts['payroll_net'],
        ]);
    }
}
