<?php

namespace App\Support\Payroll;

use App\Enums\StatutoryKind;
use App\Models\StatutoryRate;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * R-60: bracket lookup and share calculation for SSS, PhilHealth, Pag-IBIG and
 * withholding tax. Money in and out is integer centavos. A missing rate for the
 * period is an error, never a silent zero.
 */
class Statutory
{
    /**
     * Pick the newest rate per kind effective on or before the period end.
     * A gym-specific row wins a tie with a platform row on the same date.
     *
     * @param  iterable<int, StatutoryRate|array<string, mixed>>  $rates
     * @return array<string, array<string, mixed>>
     */
    public function selectRates(iterable $rates, Carbon $periodEnd): array
    {
        $selected = [];

        foreach ($rates as $rate) {
            $data = $this->normalize($rate);
            $kind = $data['kind'] instanceof StatutoryKind ? $data['kind']->value : (string) $data['kind'];
            $effective = Carbon::parse($data['effective_from'])->startOfDay();

            if ($effective->greaterThan($periodEnd->copy()->startOfDay())) {
                continue;
            }

            $current = $selected[$kind] ?? null;

            if ($current === null || $this->wins($data, $effective, $current)) {
                $data['kind'] = $kind;
                $selected[$kind] = $data;
            }
        }

        return $selected;
    }

    /** @return list<StatutoryKind> */
    public function missingKinds(array $rates, string $frequency): array
    {
        $required = [...StatutoryKind::contributions(), StatutoryKind::taxFor($frequency)];
        $missing = [];

        foreach ($required as $kind) {
            if (! isset($rates[$kind->value])) {
                $missing[] = $kind;
            }
        }

        return $missing;
    }

    /**
     * Full monthly or semi-monthly statutory result. The semi-monthly method
     * doubles the period gross to its monthly equivalent, halves the employee
     * and employer shares, and uses the semi-monthly tax table on this cutoff.
     * A non-positive gross has no employee share or withholding tax; employer
     * shares still follow the bracket table.
     *
     * @param  array<string, array<string, mixed>>  $rates
     * @return array<string, int|array<string, int>>
     */
    public function compute(int $grossCents, string $frequency, array $rates): array
    {
        $monthlyFrequency = $frequency === 'monthly';
        $monthlyPay = max(0, $monthlyFrequency ? $grossCents : $grossCents * 2);

        // A non-positive gross has no employee share or withholding tax to
        // deduct. Employer shares stay as computed: they are a gym cost, not
        // an amount taken from the employee's pay.
        $employeeDeductions = $grossCents > 0;

        $result = [
            'sss_employee' => 0, 'philhealth_employee' => 0, 'pagibig_employee' => 0,
            'sss_employer' => 0, 'ec_employer' => 0, 'philhealth_employer' => 0, 'pagibig_employer' => 0,
            'tax' => 0,
            'rate_ids' => [],
        ];

        foreach (StatutoryKind::contributions() as $kind) {
            $rate = $rates[$kind->value] ?? null;
            if ($rate === null) {
                throw new MissingStatutoryRate($kind);
            }

            $shares = $this->sharesFor($kind, $monthlyPay, $rate, $monthlyFrequency);
            $result["{$kind->value}_employee"] = $employeeDeductions ? $shares['employee'] : 0;
            $result["{$kind->value}_employer"] = $shares['employer'];
            $result['rate_ids'][$kind->value] = (int) ($rate['id'] ?? 0);

            if ($kind === StatutoryKind::Sss) {
                $result['ec_employer'] = $shares['ec'];
            }
        }

        $taxKind = StatutoryKind::taxFor($frequency);
        $taxRate = $rates[$taxKind->value] ?? null;
        if ($taxRate === null) {
            throw new MissingStatutoryRate($taxKind);
        }

        $taxable = max(0, $grossCents
            - $result['sss_employee']
            - $result['philhealth_employee']
            - $result['pagibig_employee']);

        $result['tax'] = $employeeDeductions ? $this->tax($taxable, $taxRate) : 0;
        $result['rate_ids'][$taxKind->value] = (int) ($taxRate['id'] ?? 0);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $rate
     * @return array{employee: int, employer: int, ec: int}
     */
    public function sharesFor(StatutoryKind $kind, int $monthlyPayCents, array $rate, bool $monthlyFrequency): array
    {
        $bracket = $this->bracketFor($monthlyPayCents, $rate['brackets'] ?? [], $kind);

        $employee = $this->bracketAmount($bracket, 'employee', 'employee_rate', $monthlyPayCents, $kind);
        $employer = $this->bracketAmount($bracket, 'employer', 'employer_rate', $monthlyPayCents, $kind);
        $ec = Money::toCents($bracket['ec'] ?? 0);

        if (! $monthlyFrequency) {
            $employee = Money::roundHalfUp($employee / 2);
            $employer = Money::roundHalfUp($employer / 2);
            $ec = Money::roundHalfUp($ec / 2);
        }

        return ['employee' => $employee, 'employer' => $employer, 'ec' => $ec];
    }

    /** @param  array<string, mixed>  $rate */
    public function tax(int $taxableCents, array $rate): int
    {
        $bracket = $this->bracketFor($taxableCents, $rate['brackets'] ?? [], StatutoryKind::TaxMonthly);
        $minimum = Money::toCents($bracket['min'] ?? 0);

        $tax = isset($bracket['tax'])
            ? Money::toCents($bracket['tax'])
            : Money::toCents($bracket['base'] ?? 0) + Money::multiply(max(0, $taxableCents - $minimum), (float) ($bracket['rate'] ?? 0));

        return max(0, $tax);
    }

    /**
     * @param  array<int, array<string, mixed>>  $brackets
     * @return array<string, mixed>
     */
    public function bracketFor(int $amountCents, array $brackets, StatutoryKind $kind): array
    {
        $brackets = array_values(array_filter($brackets, 'is_array'));

        if ($brackets === []) {
            throw new InvalidArgumentException("The {$kind->label()} rate has no brackets.");
        }

        usort($brackets, fn (array $a, array $b) => Money::toCents($a['min'] ?? 0) <=> Money::toCents($b['min'] ?? 0));

        foreach ($brackets as $bracket) {
            $minimum = Money::toCents($bracket['min'] ?? 0);
            $maximum = isset($bracket['max']) && $bracket['max'] !== ''
                ? Money::toCents($bracket['max'])
                : null;

            if ($amountCents >= $minimum && ($maximum === null || $amountCents <= $maximum)) {
                return $bracket;
            }
        }

        throw new InvalidArgumentException("No {$kind->label()} bracket covers ".Money::toDecimal($amountCents).'.');
    }

    /** @param  array<string, mixed>  $bracket */
    private function bracketAmount(array $bracket, string $fixedKey, string $rateKey, int $amountCents, StatutoryKind $kind): int
    {
        if (array_key_exists($fixedKey, $bracket) && $bracket[$fixedKey] !== null && $bracket[$fixedKey] !== '') {
            return Money::toCents($bracket[$fixedKey]);
        }

        if (! array_key_exists($rateKey, $bracket)) {
            return 0;
        }

        return Money::multiply($amountCents, (float) $bracket[$rateKey]);
    }

    /** @return array<string, mixed> */
    private function normalize(StatutoryRate|array $rate): array
    {
        if ($rate instanceof StatutoryRate) {
            return [
                'id' => $rate->id,
                'gym_id' => $rate->gym_id,
                'kind' => $rate->kind,
                'effective_from' => $rate->effective_from->toDateString(),
                'brackets' => $rate->brackets ?? [],
                'source' => $rate->source,
            ];
        }

        $brackets = $rate['brackets'] ?? [];
        if (is_string($brackets)) {
            $brackets = json_decode($brackets, true, flags: JSON_THROW_ON_ERROR);
        }

        return [
            'id' => $rate['id'] ?? 0,
            'gym_id' => $rate['gym_id'] ?? null,
            'kind' => $rate['kind'] ?? null,
            'effective_from' => $rate['effective_from'] ?? '1970-01-01',
            'brackets' => $brackets,
            'source' => $rate['source'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $candidate */
    private function wins(array $candidate, Carbon $effective, array $current): bool
    {
        $currentEffective = Carbon::parse($current['effective_from'])->startOfDay();

        if ($effective->greaterThan($currentEffective)) {
            return true;
        }

        if ($effective->equalTo($currentEffective)) {
            return ($candidate['gym_id'] ?? null) !== null && ($current['gym_id'] ?? null) === null;
        }

        return false;
    }
}
