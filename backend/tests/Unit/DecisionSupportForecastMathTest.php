<?php

namespace Tests\Unit;

use App\Support\DecisionSupport\Forecast;
use App\Support\Payroll\Money;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DecisionSupportForecastMathTest extends TestCase
{
    private function history(array $incoming, array $outgoing = []): array
    {
        return array_map(fn ($amount, $index) => [
            'period' => Carbon::parse('2026-01-01')->addMonths($index)->format('Y-m'),
            'income' => Money::toDecimal(Money::toCents($amount)),
            'expenses' => Money::toDecimal(Money::toCents($outgoing[$index] ?? 0)), 'net' => '0.00',
        ], $incoming, array_keys($incoming));
    }

    public function test_independent_linear_series_and_exact_future_keys(): void
    {
        $result = (new Forecast)->project($this->history([100, 200, 300], [50, 100, 150]));
        $this->assertSame('least_squares', $result['method']);
        $this->assertSame(['2026-04', '2026-05', '2026-06'], array_column($result['buckets'], 'period'));
        $this->assertSame(['400.00', '500.00', '600.00'], array_column($result['buckets'], 'income'));
        $this->assertSame(['200.00', '250.00', '300.00'], array_column($result['buckets'], 'expenses'));
        $this->assertSame(['income_r_squared' => 1.0, 'expense_r_squared' => 1.0], $result['fit']);
        $this->assertSame(['income' => '1500.00', 'expenses' => '750.00', 'net' => '750.00'], $result['totals']);
        $year = (new Forecast)->project($this->history(range(1, 12)));
        $this->assertSame(['2027-01', '2027-02', '2027-03'], array_column($year['buckets'], 'period'));
        $this->assertSame(['13.00', '14.00', '15.00'], array_column($year['buckets'], 'income'));
    }

    public function test_constant_noisy_and_short_history_fit_are_not_probability(): void
    {
        $constant = (new Forecast)->project($this->history([100, 100, 100]));
        $this->assertSame(1.0, $constant['fit']['income_r_squared']);
        $noisy = (new Forecast)->project($this->history([100, 400, 100]));
        $this->assertSame(0.0, $noisy['fit']['income_r_squared']);
        foreach ([[100], [100, 300]] as $amounts) {
            $result = (new Forecast)->project($this->history($amounts));
            $this->assertSame('average', $result['method']);
            $this->assertSame('average, not enough history', $result['method_name']);
            $this->assertNull($result['fit']['income_r_squared']);
            $this->assertSame(array_fill(0, 3, count($amounts) === 1 ? '100.00' : '200.00'), array_column($result['buckets'], 'income'));
        }
        $empty = (new Forecast)->project([]);
        $this->assertSame('no_history', $empty['method']);
        $this->assertSame([], $empty['buckets']);
        $this->assertSame(['income' => '0.00', 'expenses' => '0.00', 'net' => '0.00'], $empty['totals']);
    }

    public function test_clamps_each_series_after_half_up_rounding_but_preserves_negative_net(): void
    {
        $result = (new Forecast)->project($this->history([3, 2, 1], [2, 2, 2]));
        $this->assertSame(['0.00', '0.00', '0.00'], array_column($result['buckets'], 'income'));
        $this->assertSame(['-2.00', '-2.00', '-2.00'], array_column($result['buckets'], 'net'));
        $half = (new Forecast)->project($this->history(['0.00', '0.01'], ['0.02', '0.03']));
        $this->assertSame(['0.01', '0.01', '0.01'], array_column($half['buckets'], 'income'));
        $this->assertSame(['0.03', '0.03', '0.03'], array_column($half['buckets'], 'expenses'));
        $this->assertSame(['-0.02', '-0.02', '-0.02'], array_column($half['buckets'], 'net'));
    }
}
