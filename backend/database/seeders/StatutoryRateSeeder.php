<?php

namespace Database\Seeders;

use App\Models\StatutoryRate;
use Illuminate\Database\Seeder;

/**
 * R-60: platform statutory tables from the old Combat Sports defaults that
 * already exist in docs/existing-database/combat_sports_db.sql (effective
 * 2025-01-01). The source text says to verify against the current circulars
 * before shipping; old periods keep the row that was effective then.
 */
class StatutoryRateSeeder extends Seeder
{
    public function run(): void
    {
        $effective = '2025-01-01';

        $sss = [];
        for ($msc = 5000; $msc <= 35000; $msc += 500) {
            $minimum = $msc === 5000 ? 0 : $msc - 250;
            $maximum = $msc === 35000 ? null : $msc + 249.99;

            $sss[] = [
                'min' => number_format($minimum, 2, '.', ''),
                'max' => $maximum === null ? null : number_format($maximum, 2, '.', ''),
                'employee' => number_format($msc * 0.05, 2, '.', ''),
                'employer' => number_format(($msc * 0.10) - 10, 2, '.', ''),
                'ec' => '10.00',
            ];
        }

        $rates = [
            [
                'kind' => 'sss',
                'brackets' => $sss,
                'source' => 'Legacy Combat Sports SSS table (2025-01-01); MSC 5,000-35,000 in 500 steps, employee 5%, employer 10% incl. EC 10. Verify the current SSS circular.',
            ],
            [
                'kind' => 'philhealth',
                'brackets' => [
                    ['min' => '0.00', 'max' => '10000.00', 'employee' => '250.00', 'employer' => '250.00'],
                    ['min' => '10000.01', 'max' => '100000.00', 'employee_rate' => 0.025, 'employer_rate' => 0.025],
                    ['min' => '100000.01', 'max' => null, 'employee' => '2500.00', 'employer' => '2500.00'],
                ],
                'source' => 'Legacy Combat Sports PhilHealth table (2025-01-01); 5% premium, 2.5% employee share, floor 10,000, ceiling 100,000. Verify the current PhilHealth circular.',
            ],
            [
                'kind' => 'pagibig',
                'brackets' => [
                    ['min' => '0.00', 'max' => '1500.00', 'employee_rate' => 0.01, 'employer_rate' => 0.02],
                    ['min' => '1500.01', 'max' => '5000.00', 'employee_rate' => 0.02, 'employer_rate' => 0.02],
                    ['min' => '5000.01', 'max' => null, 'employee' => '100.00', 'employer' => '100.00'],
                ],
                'source' => 'Legacy Combat Sports Pag-IBIG table (2025-01-01); employee 1% up to 1,500 then 2%, 5,000 cap. Verify the current Pag-IBIG circular.',
            ],
            [
                'kind' => 'tax_monthly',
                'brackets' => [
                    ['min' => '0.00', 'max' => '20833.00', 'base' => '0.00', 'rate' => 0.0],
                    ['min' => '20833.01', 'max' => '33333.00', 'base' => '0.00', 'rate' => 0.15],
                    ['min' => '33333.01', 'max' => '66667.00', 'base' => '2500.00', 'rate' => 0.20],
                    ['min' => '66667.01', 'max' => '166667.00', 'base' => '10833.33', 'rate' => 0.25],
                    ['min' => '166667.01', 'max' => '666667.00', 'base' => '40833.33', 'rate' => 0.30],
                    ['min' => '666667.01', 'max' => null, 'base' => '200833.33', 'rate' => 0.35],
                ],
                'source' => 'BIR revised withholding tax table (TRAIN, effective 2023-01-01). Verify the current BIR circular.',
            ],
            [
                'kind' => 'tax_semi_monthly',
                'brackets' => [
                    ['min' => '0.00', 'max' => '10416.00', 'base' => '0.00', 'rate' => 0.0],
                    ['min' => '10416.01', 'max' => '16666.00', 'base' => '0.00', 'rate' => 0.15],
                    ['min' => '16666.01', 'max' => '33333.00', 'base' => '1250.00', 'rate' => 0.20],
                    ['min' => '33333.01', 'max' => '83333.00', 'base' => '5416.67', 'rate' => 0.25],
                    ['min' => '83333.01', 'max' => '333333.00', 'base' => '20416.67', 'rate' => 0.30],
                    ['min' => '333333.01', 'max' => null, 'base' => '100416.67', 'rate' => 0.35],
                ],
                'source' => 'BIR revised semi-monthly withholding tax table (TRAIN); monthly thresholds halved for each cutoff. Verify the current BIR circular.',
            ],
        ];

        foreach ($rates as $rate) {
            StatutoryRate::firstOrCreate(
                ['gym_id' => null, 'kind' => $rate['kind'], 'effective_from' => $effective],
                ['brackets' => $rate['brackets'], 'source' => $rate['source'], 'created_by' => null],
            );
        }
    }
}
