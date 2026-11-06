<?php

namespace Tests\Feature;

use App\Models\Budget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-67: budgets are set per category and month and shown against the ledgers. */
class BudgetTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();
    }

    public function test_budgets_upsert_per_month_and_category(): void
    {
        $response = $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [
                ['kind' => 'income', 'category' => 'membership', 'amount' => 10000],
                ['kind' => 'income', 'category' => 'session', 'amount' => 5000],
                ['kind' => 'expense', 'category' => 'rent', 'amount' => 8000],
                ['kind' => 'expense', 'category' => 'other', 'amount' => 1000],
            ],
        ])->assertOk()
            ->assertJsonPath('month', '2026-11');

        $this->assertSame('10000.00', $this->row($response->json(), 'income', 'membership')['budget']);
        $this->assertSame('5000.00', $this->row($response->json(), 'income', 'session')['budget']);
        $this->assertSame('8000.00', $this->row($response->json(), 'expense', 'rent')['budget']);
        $this->assertSame('1000.00', $this->row($response->json(), 'expense', 'other')['budget']);

        $this->assertDatabaseHas('budgets', [
            'gym_id' => $this->gym->id,
            'kind' => 'income',
            'category' => 'membership',
            'month' => '2026-11-01',
            'amount' => '10000.00',
            'updated_by' => $this->owner->id,
        ]);

        // The same category is updated in place, not inserted a second time.
        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [
                ['kind' => 'income', 'category' => 'membership', 'amount' => 12000],
                ['kind' => 'income', 'category' => 'session', 'amount' => 7000],
            ],
        ])->assertOk();

        $this->assertSame(4, DB::table('budgets')->where('gym_id', $this->gym->id)->count());
        $this->assertSame(2, DB::table('budgets')
            ->where('gym_id', $this->gym->id)
            ->where('month', '2026-11-01')
            ->where('kind', 'income')
            ->count());

        // Another month gets its own rows.
        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-12',
            'budgets' => [
                ['kind' => 'income', 'category' => 'membership', 'amount' => 13000],
            ],
        ])->assertOk();

        $november = $this->actingAs($this->owner)
            ->getJson($this->url('finance/budgets?month=2026-11'))
            ->assertOk()
            ->assertJsonPath('month', '2026-11');

        $this->assertSame('12000.00', $this->row($november->json(), 'income', 'membership')['budget']);
        $this->assertSame(5, DB::table('budgets')->where('gym_id', $this->gym->id)->count());

        $stored = Budget::where('kind', 'income')->where('category', 'membership')
            ->where('month', '2026-11-01')->firstOrFail();
        $this->assertSame('2026-11-01', $stored->month->toDateString());
        $this->assertSame('12000.00', $stored->amount);
        $this->assertSame('Owen Reyes', trim("{$stored->updatedBy->first_name} {$stored->updatedBy->last_name}"));
    }

    public function test_actual_equals_the_source_sums_and_difference_uses_budget_minus_actual(): void
    {
        $this->payment('membership', '1000.00', '2026-11-02');
        $this->payment('membership', '500.00', '2026-11-03');
        $this->payment('membership', '-200.00', '2026-11-04');
        $this->payment('session', '300.00', '2026-11-05');
        $this->payment('private_coaching', '200.00', '2026-11-06');
        $this->payment('walk_in', '100.00', '2026-11-07');
        $this->payment('merchandise', '50.00', '2026-11-08');
        $this->payment('other', '25.00', '2026-11-09');
        $this->payment('membership', '999.00', '2026-10-31');

        $this->expense('rent', '800.00', '2026-11-02');
        $this->expense('rent', '200.00', '2026-11-03');
        $this->expense('rent', '-100.00', '2026-11-04');
        $this->expense('utilities', '300.00', '2026-11-05');
        $this->expense('payroll', '3000.00', '2026-11-06', 'payroll_run', 10);
        $this->expense('purchases', '500.00', '2026-11-07', 'goods_receipt', 10);
        $this->expense('maintenance', '55.00', '2026-11-30');
        $this->expense('rent', '777.00', '2026-10-31');

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [
                ['kind' => 'income', 'category' => 'membership', 'amount' => 2000],
                ['kind' => 'income', 'category' => 'other', 'amount' => 100],
                ['kind' => 'expense', 'category' => 'rent', 'amount' => 1000],
                ['kind' => 'expense', 'category' => 'utilities', 'amount' => 100],
                ['kind' => 'expense', 'category' => 'other', 'amount' => 0],
            ],
        ])->assertOk();

        $response = $this->actingAs($this->owner)
            ->getJson($this->url('finance/budgets?month=2026-11'))
            ->assertOk();

        $payload = $response->json();
        $this->assertSame(
            array_keys(config('finance.income_types')),
            $payload['categories']['income'],
        );
        $this->assertSame(
            array_keys(config('finance.expense_categories')),
            $payload['categories']['expense'],
        );
        $this->assertCount(
            count(config('finance.income_types')) + count(config('finance.expense_categories')),
            $payload['budgets'],
        );

        $membership = $this->row($payload, 'income', 'membership');
        $this->assertSame('2000.00', $membership['budget']);
        $this->assertSame('1300.00', $membership['actual']);
        $this->assertSame('700.00', $membership['difference']);
        $this->assertSame('65.00', $membership['percentage']);

        $otherIncome = $this->row($payload, 'income', 'other');
        $this->assertSame('25.00', $otherIncome['actual']);
        $this->assertSame('75.00', $otherIncome['difference']);
        $this->assertSame('25.00', $otherIncome['percentage']);

        $rent = $this->row($payload, 'expense', 'rent');
        $this->assertSame('900.00', $rent['actual']);
        $this->assertSame('100.00', $rent['difference']);
        $this->assertSame('90.00', $rent['percentage']);

        $utilities = $this->row($payload, 'expense', 'utilities');
        $this->assertSame('300.00', $utilities['actual']);
        $this->assertSame('-200.00', $utilities['difference']);
        $this->assertSame('300.00', $utilities['percentage']);

        $otherExpense = $this->row($payload, 'expense', 'other');
        $this->assertSame('0.00', $otherExpense['actual']);
        $this->assertSame('0.00', $otherExpense['difference']);
        $this->assertNull($otherExpense['percentage']);

        // A row on the last day of the month is inside the month.
        $this->assertSame('55.00', $this->row($payload, 'expense', 'maintenance')['actual']);

        // Every row agrees with a direct sum over the source ledger.
        foreach ($payload['categories']['income'] as $category) {
            $this->assertSame(
                $this->money(DB::table('payments')
                    ->where('gym_id', $this->gym->id)
                    ->where('income_type', $category)
                    ->where('paid_at', '>=', '2026-11-01 00:00:00')
                    ->where('paid_at', '<', '2026-12-01 00:00:00')
                    ->sum('amount')),
                $this->row($payload, 'income', $category)['actual'],
                $category,
            );
        }

        foreach ($payload['categories']['expense'] as $category) {
            $this->assertSame(
                $this->money(DB::table('expenses')
                    ->where('gym_id', $this->gym->id)
                    ->where('category', $category)
                    ->where('incurred_on', '>=', '2026-11-01')
                    ->where('incurred_on', '<', '2026-12-01')
                    ->sum('amount')),
                $this->row($payload, 'expense', $category)['actual'],
                $category,
            );
        }
    }

    public function test_model_normalises_the_month_key_to_the_first_day(): void
    {
        $budget = Budget::create([
            'kind' => 'income',
            'category' => 'membership',
            'month' => '2026-10-18',
            'amount' => '2500.50',
            'updated_by' => $this->owner->id,
        ]);

        $this->assertDatabaseHas('budgets', [
            'gym_id' => $this->gym->id,
            'kind' => 'income',
            'category' => 'membership',
            'month' => '2026-10-01',
            'amount' => '2500.50',
        ]);
        $this->assertSame('2026-10-01', $budget->refresh()->month->toDateString());
        $this->assertSame('2500.50', $budget->amount);
    }

    public function test_budget_validation_and_exact_permissions(): void
    {
        $viewer = $this->employeeWithFinanceRole(['finance.budgets.view']);
        $manager = $this->employeeWithFinanceRole(['finance.budgets.manage']);
        $plain = $this->employeeWithFinanceRole([]);

        $payload = [
            'month' => '2026-11',
            'budgets' => [['kind' => 'expense', 'category' => 'rent', 'amount' => 1000]],
        ];

        $this->actingAs($viewer)->getJson($this->url('finance/budgets?month=2026-11'))->assertOk();
        $this->actingAs($viewer)->putJson($this->url('finance/budgets'), $payload)->assertForbidden();

        $this->actingAs($manager)->putJson($this->url('finance/budgets'), $payload)->assertOk();
        $this->actingAs($manager)->getJson($this->url('finance/budgets?month=2026-11'))->assertForbidden();
        $this->actingAs($plain)->getJson($this->url('finance/budgets?month=2026-11'))->assertForbidden();

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => 'not-a-month',
            'budgets' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('month');

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [['kind' => 'savings', 'category' => 'rent', 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('budgets.0.kind');

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [['kind' => 'income', 'category' => 'rent', 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('budgets.0.category');

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [['kind' => 'expense', 'category' => 'rent', 'amount' => -1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('budgets.0.amount');

        $this->actingAs($this->owner)->putJson($this->url('finance/budgets'), [
            'month' => '2026-11',
            'budgets' => [
                ['kind' => 'expense', 'category' => 'rent', 'amount' => 1000],
                ['kind' => 'expense', 'category' => 'rent', 'amount' => 2000],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('budgets.1.category');

        $this->assertSame(1, DB::table('budgets')->where('gym_id', $this->gym->id)->count());
    }

    /** @param array<string, mixed> $payload */
    private function row(array $payload, string $kind, string $category): array
    {
        $row = collect($payload['budgets'])->first(
            fn (array $entry) => $entry['kind'] === $kind && $entry['category'] === $category,
        );

        $this->assertNotNull($row, "{$kind}/{$category} is missing from the budget rows.");

        return $row;
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

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
