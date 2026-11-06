<?php

namespace Tests\Unit;

use App\Enums\StatutoryKind;
use App\Support\Payroll\MissingStatutoryRate;
use App\Support\Payroll\Statutory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** R-60: bracket edges, effective dates, shares and the two tax frequencies. */
class StatutoryTest extends TestCase
{
    public function test_every_sss_bracket_edge_and_the_ceiling(): void
    {
        $rate = $this->sssRate();

        $atCeilingEdge = $this->statutory->sharesFor(StatutoryKind::Sss, 524_999, $rate, true);
        $this->assertSame(25_000, $atCeilingEdge['employee']);
        $this->assertSame(49_000, $atCeilingEdge['employer']);
        $this->assertSame(1_000, $atCeilingEdge['ec']);

        $nextBracket = $this->statutory->sharesFor(StatutoryKind::Sss, 525_000, $rate, true);
        $this->assertSame(27_500, $nextBracket['employee']);
        $this->assertSame(54_000, $nextBracket['employer']);

        $topBracket = $this->statutory->sharesFor(StatutoryKind::Sss, 575_000, $rate, true);
        $this->assertSame(30_000, $topBracket['employee']);
        $this->assertSame(59_000, $topBracket['employer']);

        $aboveTop = $this->statutory->sharesFor(StatutoryKind::Sss, 99_999_999, $rate, true);
        $this->assertSame(30_000, $aboveTop['employee']);
        $this->assertSame(59_000, $aboveTop['employer']);
    }

    public function test_philhealth_floor_and_ceiling_edges(): void
    {
        $rate = $this->philhealthRate();

        $belowFloor = $this->statutory->sharesFor(StatutoryKind::Philhealth, 900_000, $rate, true);
        $this->assertSame(25_000, $belowFloor['employee']);
        $this->assertSame(25_000, $belowFloor['employer']);

        $atFloor = $this->statutory->sharesFor(StatutoryKind::Philhealth, 1_000_000, $rate, true);
        $this->assertSame(25_000, $atFloor['employee']);

        $inside = $this->statutory->sharesFor(StatutoryKind::Philhealth, 2_000_000, $rate, true);
        $this->assertSame(50_000, $inside['employee']);
        $this->assertSame(50_000, $inside['employer']);

        $atCeiling = $this->statutory->sharesFor(StatutoryKind::Philhealth, 10_000_000, $rate, true);
        $this->assertSame(250_000, $atCeiling['employee']);

        $aboveCeiling = $this->statutory->sharesFor(StatutoryKind::Philhealth, 20_000_000, $rate, true);
        $this->assertSame(250_000, $aboveCeiling['employee']);
        $this->assertSame(250_000, $aboveCeiling['employer']);
    }

    public function test_pagibig_rate_change_and_cap_edges(): void
    {
        $rate = $this->pagibigRate();

        $low = $this->statutory->sharesFor(StatutoryKind::Pagibig, 150_000, $rate, true);
        $this->assertSame(1_500, $low['employee']);
        $this->assertSame(3_000, $low['employer']);

        $high = $this->statutory->sharesFor(StatutoryKind::Pagibig, 150_001, $rate, true);
        $this->assertSame(3_000, $high['employee']);
        $this->assertSame(3_000, $high['employer']);

        $atCap = $this->statutory->sharesFor(StatutoryKind::Pagibig, 500_000, $rate, true);
        $this->assertSame(10_000, $atCap['employee']);

        $aboveCap = $this->statutory->sharesFor(StatutoryKind::Pagibig, 500_001, $rate, true);
        $this->assertSame(10_000, $aboveCap['employee']);
        $this->assertSame(10_000, $aboveCap['employer']);
    }

    public function test_tax_brackets_are_inclusive_at_each_edge(): void
    {
        $rate = $this->taxRate();

        $this->assertSame(0, $this->statutory->tax(1_000_000, $rate));
        $this->assertSame(0, $this->statutory->tax(1_000_001, $rate));
        $this->assertSame(150_000, $this->statutory->tax(2_000_000, $rate));
        $this->assertSame(150_000, $this->statutory->tax(2_000_001, $rate));
        $this->assertSame(350_000, $this->statutory->tax(3_000_000, $rate));
    }

    public function test_the_newest_rate_effective_on_or_before_the_period_end_wins(): void
    {
        $rows = [
            ['id' => 1, 'kind' => 'sss', 'gym_id' => null, 'effective_from' => '2024-01-01', 'brackets' => [], 'source' => '2024'],
            ['id' => 2, 'kind' => 'sss', 'gym_id' => null, 'effective_from' => '2025-01-01', 'brackets' => [], 'source' => '2025'],
            ['id' => 3, 'kind' => 'sss', 'gym_id' => null, 'effective_from' => '2026-01-01', 'brackets' => [], 'source' => '2026'],
            ['id' => 4, 'kind' => 'sss', 'gym_id' => 8, 'effective_from' => '2025-01-01', 'brackets' => [], 'source' => 'gym override'],
        ];

        $selected = $this->statutory->selectRates($rows, Carbon::parse('2025-06-30'));

        $this->assertSame(4, $selected['sss']['id']);
        $this->assertSame('gym override', $selected['sss']['source']);

        $selected = $this->statutory->selectRates($rows, Carbon::parse('2024-12-31'));
        $this->assertSame(1, $selected['sss']['id']);
    }

    public function test_semi_monthly_shares_are_halved_and_the_semi_monthly_tax_table_is_used(): void
    {
        $result = $this->statutory->compute(1_500_000, 'semi_monthly', $this->computeRates());

        $this->assertSame(15_000, $result['sss_employee']);
        $this->assertSame(29_500, $result['sss_employer']);
        $this->assertSame(500, $result['ec_employer']);
        $this->assertSame(37_500, $result['philhealth_employee']);
        $this->assertSame(37_500, $result['philhealth_employer']);
        $this->assertSame(5_000, $result['pagibig_employee']);
        $this->assertSame(5_000, $result['pagibig_employer']);
        $this->assertSame(66_375, $result['tax']);
        $this->assertSame(1, $result['rate_ids']['sss']);
    }

    public function test_a_monthly_run_uses_full_monthly_shares_and_the_monthly_tax_table(): void
    {
        $result = $this->statutory->compute(3_000_000, 'monthly', $this->computeRates());

        $this->assertSame(30_000, $result['sss_employee']);
        $this->assertSame(59_000, $result['sss_employer']);
        $this->assertSame(1_000, $result['ec_employer']);
        $this->assertSame(75_000, $result['philhealth_employee']);
        $this->assertSame(10_000, $result['pagibig_employee']);
        $this->assertSame(132_750, $result['tax']);
    }

    public function test_no_employee_share_or_tax_when_gross_is_zero_or_negative(): void
    {
        foreach ([0, -1_500_000] as $gross) {
            $result = $this->statutory->compute($gross, 'semi_monthly', $this->computeRates());

            $this->assertSame(0, $result['sss_employee']);
            $this->assertSame(0, $result['philhealth_employee']);
            $this->assertSame(0, $result['pagibig_employee']);
            $this->assertSame(0, $result['tax']);
        }
    }

    public function test_a_missing_rate_throws_for_the_period(): void
    {
        $this->expectException(MissingStatutoryRate::class);
        $this->expectExceptionMessage('SSS');

        $this->statutory->compute(1_500_000, 'semi_monthly', [
            'philhealth' => $this->philhealthRate(),
            'pagibig' => $this->pagibigRate(),
            'tax_semi_monthly' => $this->taxRate(),
        ]);
    }

    private Statutory $statutory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statutory = new Statutory;
    }

    /** @return array<string, mixed> */
    private function sssRate(): array
    {
        return [
            'id' => 1,
            'kind' => 'sss',
            'effective_from' => '2025-01-01',
            'brackets' => [
                ['min' => '0.00', 'max' => '5249.99', 'employee' => '250.00', 'employer' => '490.00', 'ec' => '10.00'],
                ['min' => '5250.00', 'max' => '5749.99', 'employee' => '275.00', 'employer' => '540.00', 'ec' => '10.00'],
                ['min' => '5750.00', 'max' => null, 'employee' => '300.00', 'employer' => '590.00', 'ec' => '10.00'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function philhealthRate(): array
    {
        return [
            'id' => 2,
            'kind' => 'philhealth',
            'effective_from' => '2025-01-01',
            'brackets' => [
                ['min' => '0.00', 'max' => '9999.99', 'employee' => '250.00', 'employer' => '250.00'],
                ['min' => '10000.00', 'max' => '100000.00', 'employee_rate' => 0.025, 'employer_rate' => 0.025],
                ['min' => '100000.01', 'max' => null, 'employee' => '2500.00', 'employer' => '2500.00'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function pagibigRate(): array
    {
        return [
            'id' => 3,
            'kind' => 'pagibig',
            'effective_from' => '2025-01-01',
            'brackets' => [
                ['min' => '0.00', 'max' => '1500.00', 'employee_rate' => 0.01, 'employer_rate' => 0.02],
                ['min' => '1500.01', 'max' => '5000.00', 'employee_rate' => 0.02, 'employer_rate' => 0.02],
                ['min' => '5000.01', 'max' => null, 'employee' => '100.00', 'employer' => '100.00'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function taxRate(): array
    {
        return [
            'id' => 4,
            'kind' => 'tax_monthly',
            'effective_from' => '2025-01-01',
            'brackets' => [
                ['min' => '0.00', 'max' => '10000.00', 'base' => '0.00', 'rate' => 0.0],
                ['min' => '10000.01', 'max' => '20000.00', 'base' => '0.00', 'rate' => 0.15],
                ['min' => '20000.01', 'max' => null, 'base' => '1500.00', 'rate' => 0.20],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function computeRates(): array
    {
        return [
            'sss' => [
                'id' => 1,
                'kind' => 'sss',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee' => '300.00', 'employer' => '590.00', 'ec' => '10.00'],
                ],
            ],
            'philhealth' => [
                'id' => 2,
                'kind' => 'philhealth',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee_rate' => 0.025, 'employer_rate' => 0.025],
                ],
            ],
            'pagibig' => [
                'id' => 3,
                'kind' => 'pagibig',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => null, 'employee' => '100.00', 'employer' => '100.00'],
                ],
            ],
            'tax_semi_monthly' => [
                'id' => 4,
                'kind' => 'tax_semi_monthly',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => '10000.00', 'base' => '0.00', 'rate' => 0.0],
                    ['min' => '10000.01', 'max' => null, 'base' => '0.00', 'rate' => 0.15],
                ],
            ],
            'tax_monthly' => [
                'id' => 5,
                'kind' => 'tax_monthly',
                'effective_from' => '2025-01-01',
                'brackets' => [
                    ['min' => '0.00', 'max' => '20000.00', 'base' => '0.00', 'rate' => 0.0],
                    ['min' => '20000.01', 'max' => null, 'base' => '0.00', 'rate' => 0.15],
                ],
            ],
        ];
    }
}
