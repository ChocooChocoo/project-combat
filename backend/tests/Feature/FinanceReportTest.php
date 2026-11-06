<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Finance\Reports;
use App\Support\Sql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-68: every report equals the records it reads and refuses a reversed range. */
class FinanceReportTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();
    }

    public function test_cash_flow_counts_payments_and_actual_cash_out_only(): void
    {
        $this->payment('membership', '1000.00', '2026-11-01');
        $this->payment('session', '500.00', '2026-11-02');
        $this->payment('membership', '-100.00', '2026-11-07');
        $this->payment('membership', '250.00', '2026-11-30');
        $this->payment('membership', '999.00', '2026-10-31');

        $this->expense('rent', '200.00', '2026-11-03');
        $this->expense('payroll', '3000.00', '2026-11-04', 'payroll_run', 20);
        $this->expense('purchases', '5000.00', '2026-11-05', 'goods_receipt', 20);
        $this->expense('equipment', '700.00', '2026-11-06');
        $this->expense('maintenance', '55.00', '2026-11-30');
        $this->expense('utilities', '100.00', '2026-12-01');

        $this->supplierPayment('1200.00', '2026-11-06');
        $this->supplierPayment('400.00', '2026-10-30');

        $response = $this->actingAs($this->owner)
            ->getJson($this->url('finance/reports/cash-flow?from=2026-11-01&to=2026-11-30'))
            ->assertOk();

        $payload = $response->json();
        $this->assertCount(30, $payload['buckets']);
        $this->assertSame('2026-11-01', $payload['buckets'][0]['period']);
        $this->assertSame('2026-11-30', $payload['buckets'][29]['period']);

        $this->assertSame('1000.00', $this->bucket($payload, '2026-11-01')['in']);
        $this->assertSame('0.00', $this->bucket($payload, '2026-11-01')['out']);
        $this->assertSame('1000.00', $this->bucket($payload, '2026-11-01')['net']);
        $this->assertSame('200.00', $this->bucket($payload, '2026-11-03')['out']);
        $this->assertSame('3000.00', $this->bucket($payload, '2026-11-04')['out']);

        // The received purchase is not cash out; the supplier payment is.
        $this->assertSame('0.00', $this->bucket($payload, '2026-11-05')['out']);
        $this->assertSame('1900.00', $this->bucket($payload, '2026-11-06')['out']);
        $this->assertSame('55.00', $this->bucket($payload, '2026-11-30')['out']);

        $this->assertSame('-100.00', $this->bucket($payload, '2026-11-07')['in']);
        $this->assertSame('250.00', $this->bucket($payload, '2026-11-30')['in']);

        $this->assertSame('1650.00', $payload['totals']['in']);
        $this->assertSame('5155.00', $payload['totals']['out']);
        $this->assertSame('-3505.00', $payload['totals']['net']);

        $this->assertSame(
            $payload['totals']['in'],
            $this->money(array_sum(array_map(fn (array $row) => (float) $row['in'], $payload['buckets']))),
        );
        $this->assertSame(
            $payload['totals']['out'],
            $this->money(array_sum(array_map(fn (array $row) => (float) $row['out'], $payload['buckets']))),
        );
    }

    public function test_income_against_expenses_includes_every_expense(): void
    {
        $this->payment('membership', '1000.00', '2026-11-02');
        $this->payment('session', '500.00', '2026-11-03');

        $this->expense('rent', '200.00', '2026-11-03');
        $this->expense('purchases', '5000.00', '2026-11-04', 'goods_receipt', 30);
        $this->expense('payroll', '3000.00', '2026-11-05', 'payroll_run', 30);
        $this->supplierPayment('1200.00', '2026-11-06');

        $payload = $this->actingAs($this->owner)
            ->getJson($this->url('finance/reports/income-expense?from=2026-11-01&to=2026-11-10'))
            ->assertOk()
            ->json();

        $this->assertCount(10, $payload['buckets']);
        $this->assertSame('1500.00', $payload['totals']['income']);
        $this->assertSame('8200.00', $payload['totals']['expenses']);
        $this->assertSame('-6700.00', $payload['totals']['net']);

        $this->assertSame('5000.00', $this->bucket($payload, '2026-11-04')['expenses']);
        $this->assertSame('0.00', $this->bucket($payload, '2026-11-06')['expenses']);
        $this->assertSame('1000.00', $this->bucket($payload, '2026-11-02')['income']);
    }

    public function test_payroll_summary_reads_released_lines_not_cached_totals(): void
    {
        $this->releasedRun('2026-11-01', '2026-11-15', '2026-11-16 10:00:00', [
            ['gross' => '10000.00', 'net' => '9000.00', 'sss_employer' => '500.00', 'ec_employer' => '10.00', 'philhealth_employer' => '250.00', 'pagibig_employer' => '100.00'],
            ['gross' => '5000.00', 'net' => '4500.00', 'sss_employer' => '200.00', 'ec_employer' => '5.00', 'philhealth_employer' => '150.00', 'pagibig_employer' => '75.00'],
        ], cached: [
            'gross_total' => '999999.00',
            'net_total' => '888888.00',
            'employer_statutory_total' => '777777.00',
        ]);

        $this->payrollRun('2026-11-16', '2026-11-30', null, [['gross' => '7000.00', 'net' => '6000.00']], 'in_review');
        $this->payrollRun('2026-11-01', '2026-11-10', null, [['gross' => '3000.00', 'net' => '2500.00']], 'draft');
        $this->releasedRun('2026-12-01', '2026-12-15', '2026-12-16 10:00:00', [
            ['gross' => '1000.00', 'net' => '900.00'],
        ]);

        $payload = $this->actingAs($this->owner)
            ->getJson($this->url('finance/reports/payroll?from=2026-11-01&to=2026-11-30'))
            ->assertOk()
            ->json();

        $this->assertCount(30, $payload['buckets']);
        $this->assertSame('15000.00', $payload['totals']['gross']);
        $this->assertSame('13500.00', $payload['totals']['net']);
        $this->assertSame('1290.00', $payload['totals']['employer_statutory']);
        $this->assertSame(2, $payload['totals']['count']);

        $bucket = $this->bucket($payload, '2026-11-15');
        $this->assertSame('15000.00', $bucket['gross']);
        $this->assertSame('13500.00', $bucket['net']);
        $this->assertSame('1290.00', $bucket['employer_statutory']);
        $this->assertSame(2, $bucket['count']);

        // The deliberately wrong cached totals are not used.
        $this->assertNotSame('999999.00', $payload['totals']['gross']);
        $this->assertSame('0.00', $this->bucket($payload, '2026-11-30')['gross']);
        $this->assertSame(0, $this->bucket($payload, '2026-11-30')['count']);
    }

    public function test_payout_history_is_released_net_plus_supplier_payments(): void
    {
        $this->releasedRun('2026-11-01', '2026-11-15', '2026-11-16 10:00:00', [
            ['gross' => '10000.00', 'net' => '9000.00'],
            ['gross' => '5000.00', 'net' => '4500.00'],
        ], cached: [
            'net_total' => '888888.00',
        ]);

        $this->payrollRun('2026-11-16', '2026-11-30', null, [['gross' => '7000.00', 'net' => '6000.00']], 'in_review');

        $this->supplierPayment('1200.00', '2026-11-06');
        $this->supplierPayment('300.00', '2026-11-20');
        $this->supplierPayment('400.00', '2026-10-30');

        $payload = $this->actingAs($this->owner)
            ->getJson($this->url('finance/reports/payouts?from=2026-11-01&to=2026-11-30'))
            ->assertOk()
            ->json();

        $this->assertCount(30, $payload['buckets']);
        $this->assertSame('13500.00', $payload['totals']['payroll_net']);
        $this->assertSame('1500.00', $payload['totals']['supplier_payments']);
        $this->assertSame('15000.00', $payload['totals']['total']);

        $this->assertSame('13500.00', $this->bucket($payload, '2026-11-16')['payroll_net']);
        $this->assertSame('1200.00', $this->bucket($payload, '2026-11-06')['supplier_payments']);
        $this->assertSame('300.00', $this->bucket($payload, '2026-11-20')['supplier_payments']);
        $this->assertSame('0.00', $this->bucket($payload, '2026-11-30')['payroll_net']);
    }

    public function test_buckets_switch_from_daily_to_monthly_above_62_days(): void
    {
        $this->payment('membership', '1000.00', '2026-01-10');

        $short = 'finance/reports/cash-flow?from=2026-01-01&to=2026-03-03';
        $shortPayload = $this->actingAs($this->owner)->getJson($this->url($short))->assertOk()->json();
        $this->assertCount(62, $shortPayload['buckets']);
        $this->assertSame('2026-01-01', $shortPayload['buckets'][0]['period']);
        $this->assertSame('2026-03-03', $shortPayload['buckets'][61]['period']);
        $this->assertSame('1000.00', $this->bucket($shortPayload, '2026-01-10')['in']);

        $long = [
            'finance/reports/cash-flow?from=2026-01-01&to=2026-03-04',
            'finance/reports/income-expense?from=2026-01-01&to=2026-03-04',
            'finance/reports/payroll?from=2026-01-01&to=2026-03-04',
            'finance/reports/payouts?from=2026-01-01&to=2026-03-04',
        ];

        foreach ($long as $path) {
            $payload = $this->actingAs($this->owner)->getJson($this->url($path))->assertOk()->json();

            $this->assertSame(
                ['2026-01', '2026-02', '2026-03'],
                array_column($payload['buckets'], 'period'),
                $path,
            );
        }
    }

    public function test_month_helper_has_a_sqlite_and_a_mysql_form(): void
    {
        $this->assertSame("strftime('%Y-%m', payments.paid_at)", Sql::monthFor('sqlite', 'payments.paid_at'));
        $this->assertSame("DATE_FORMAT(payments.paid_at, '%Y-%m')", Sql::monthFor('mysql', 'payments.paid_at'));
        $this->assertSame("DATE_FORMAT(payments.paid_at, '%Y-%m')", Sql::monthFor('mariadb', 'payments.paid_at'));
        $this->assertSame(
            DB::connection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', payments.paid_at)"
                : "DATE_FORMAT(payments.paid_at, '%Y-%m')",
            Sql::month('payments.paid_at'),
        );

        $this->expectException(\InvalidArgumentException::class);
        Sql::monthFor('pgsql', 'payments.paid_at');
    }

    public function test_reversed_ranges_are_refused(): void
    {
        foreach ([
            'finance/reports/cash-flow',
            'finance/reports/income-expense',
            'finance/reports/payroll',
            'finance/reports/payouts',
        ] as $path) {
            $this->actingAs($this->owner)
                ->getJson($this->url("{$path}?from=2026-11-30&to=2026-11-01"))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('to');
        }

        try {
            app(Reports::class)->cashFlow($this->gym, '2026-11-30', '2026-11-01');
            $this->fail('The support layer accepted a reversed range.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('to', $error->errors());
        }
    }

    public function test_reports_need_the_exact_view_permission(): void
    {
        $viewer = $this->employeeWithFinanceRole(['finance.reports.view']);
        $plain = $this->employeeWithFinanceRole([]);

        foreach ([
            'finance/reports/cash-flow',
            'finance/reports/income-expense',
            'finance/reports/payroll',
            'finance/reports/payouts',
        ] as $path) {
            $url = $this->url("{$path}?from=2026-11-01&to=2026-11-30");
            $this->actingAs($viewer)->getJson($url)->assertOk();
            $this->actingAs($plain)->getJson($url)->assertForbidden();
        }
    }

    /** @param  list<array<string, string>>  $lines */
    private function releasedRun(string $startsOn, string $endsOn, string $releasedAt, array $lines, array $cached = []): PayrollRun
    {
        return $this->payrollRun($startsOn, $endsOn, $releasedAt, $lines, 'released', $cached);
    }

    /** @param  list<array<string, string>>  $lines */
    private function payrollRun(
        string $startsOn,
        string $endsOn,
        ?string $releasedAt,
        array $lines,
        string $status,
        array $cached = [],
    ): PayrollRun {
        $run = $this->gym->payrollRuns()->create([
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
            'steps' => ['owner'],
            'current_step' => 0,
            'round' => 1,
            'prepared_by' => $this->owner->id,
            'released_by' => $status === 'released' ? $this->owner->id : null,
            'released_at' => $status === 'released' ? $releasedAt : null,
            'gross_total' => $cached['gross_total'] ?? '0.00',
            'net_total' => $cached['net_total'] ?? '0.00',
            'employer_statutory_total' => $cached['employer_statutory_total'] ?? '0.00',
        ]);

        foreach ($lines as $index => $line) {
            $employee = User::factory()->create();

            $run->lines()->create([
                'user_id' => $employee->id,
                'name' => "Employee {$index}",
                'gross_pay' => $line['gross'],
                'net_pay' => $line['net'],
                'sss_employer' => $line['sss_employer'] ?? '0.00',
                'ec_employer' => $line['ec_employer'] ?? '0.00',
                'philhealth_employer' => $line['philhealth_employer'] ?? '0.00',
                'pagibig_employer' => $line['pagibig_employer'] ?? '0.00',
            ]);
        }

        return $run;
    }

    private function payment(string $type, string $amount, string $date): void
    {
        $this->gym->payments()->create([
            'user_id' => $this->member->id,
            'amount' => $amount,
            'method' => 'cash',
            'paid_at' => $date.' 10:00:00',
            'income_type' => $type,
        ]);
    }

    private function expense(string $category, string $amount, string $date, ?string $sourceType = null, ?int $sourceId = null): void
    {
        $this->gym->expenses()->create([
            'category' => $category,
            'amount' => $amount,
            'incurred_on' => $date,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'recorded_by' => $this->owner->id,
        ]);
    }

    private function supplierPayment(string $amount, string $paidOn): void
    {
        $supplier = Supplier::where('name', 'Report test supplier')->first()
            ?? $this->gym->suppliers()->create(['name' => 'Report test supplier']);

        $this->gym->supplierPayments()->create([
            'supplier_id' => $supplier->id,
            'amount' => $amount,
            'method' => 'bank',
            'paid_on' => $paidOn,
            'recorded_by' => $this->owner->id,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function bucket(array $payload, string $period): array
    {
        $bucket = collect($payload['buckets'])->firstWhere('period', $period);

        $this->assertNotNull($bucket, "The {$period} bucket is missing.");

        return $bucket;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
