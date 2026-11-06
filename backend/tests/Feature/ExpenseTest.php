<?php

namespace Tests\Feature;

use App\Support\CurrentGym;
use App\Support\Finance\Expenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-64: manual expenses, their corrections, and automatic source keys. */
class ExpenseTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();
    }

    public function test_an_expense_records_its_details_and_can_be_filtered(): void
    {
        foreach ([
            ['rent', 1000, '2026-11-01', 'bank', 'November rent'],
            ['rent', 500, '2026-11-02', 'cash', 'Extra storage'],
            ['utilities', 300, '2026-10-15', 'ewallet', 'October power'],
        ] as [$category, $amount, $date, $method, $note]) {
            $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
                'category' => $category, 'amount' => $amount, 'incurred_on' => $date,
                'method' => $method, 'note' => $note,
            ])->assertCreated()
                ->assertJsonPath('expense.category', $category)
                ->assertJsonPath('expense.recorded_by_name', 'Owen Reyes')
                ->assertJsonPath('expense.incurred_on', $date);
        }

        $response = $this->actingAs($this->owner)
            ->getJson($this->url('finance/expenses?from=2026-11-01&to=2026-11-30&category=rent'))
            ->assertOk();

        $response->assertJsonCount(2, 'expenses')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('totals.0.category', 'rent')
            ->assertJsonPath('totals.0.amount', '1500.00');

        $this->assertSame(1500.0, collect($response->json('expenses'))->sum(fn (array $row) => (float) $row['amount']));
    }

    public function test_a_correction_is_a_negative_linked_row_and_happens_once(): void
    {
        $original = $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'supplies', 'amount' => 1000, 'incurred_on' => '2026-11-01',
            'method' => 'card', 'note' => 'Cleaning supplies',
        ])->assertCreated()->json('expense');

        $correction = $this->actingAs($this->owner)
            ->postJson($this->url("finance/expenses/{$original['id']}/correct"), ['reason' => 'Wrong branch'])
            ->assertCreated()
            ->assertJsonPath('expense.amount', '-1000.00')
            ->assertJsonPath('expense.corrects_id', $original['id'])
            ->assertJsonPath('expense.category', 'supplies')
            ->assertJsonPath('expense.method', 'card');

        $this->assertStringContainsString('Wrong branch', $correction->json('expense.note'));
        $this->assertSame(today()->toDateString(), $correction->json('expense.incurred_on'));

        $this->actingAs($this->owner)
            ->postJson($this->url("finance/expenses/{$original['id']}/correct"), ['reason' => 'Again'])
            ->assertStatus(409);
        $this->actingAs($this->owner)
            ->postJson($this->url("finance/expenses/{$correction->json('expense.id')}/correct"), ['reason' => 'Again'])
            ->assertStatus(409);

        $this->assertSame(0.0, (float) $this->actingAs($this->owner)
            ->getJson($this->url('finance/expenses'))->assertOk()
            ->json('totals.0.amount'));
    }

    public function test_expenses_are_append_only(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'rent', 'amount' => 1000, 'incurred_on' => '2026-11-01',
        ])->assertCreated();

        app(CurrentGym::class)->set($this->gym);
        $expense = $this->gym->expenses()->firstOrFail();

        try {
            $expense->update(['note' => 'Changed']);
            $this->fail('An expense was changed.');
        } catch (LogicException) {
        }

        try {
            $expense->delete();
            $this->fail('An expense was deleted.');
        } catch (LogicException) {
        }

        $this->assertSame('2026-11-01', $expense->refresh()->incurred_on->toDateString());
    }

    public function test_an_automatic_source_key_can_only_be_posted_once_per_category(): void
    {
        $expenses = app(Expenses::class);

        $first = $expenses->post($this->gym, 'purchases', '1200.00', today(), 'goods_receipt', 77, $this->owner);
        $this->assertSame(77, $first->source_id);
        $this->assertSame('Owen Reyes', trim("{$first->recordedBy->first_name} {$first->recordedBy->last_name}"));

        try {
            $expenses->post($this->gym, 'purchases', '1200.00', today(), 'goods_receipt', 77, $this->owner);
            $this->fail('The same source was posted twice.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('source_type', $error->errors());
        }

        // The unique key includes the category, so another cost for the same receipt is allowed.
        $expenses->post($this->gym, 'equipment', '500.00', today(), 'goods_receipt', 77, $this->owner);

        // Manual rows have null source keys, and many nulls are allowed.
        $expenses->post($this->gym, 'rent', '100.00', today(), null, null, $this->owner);
        $expenses->post($this->gym, 'rent', '200.00', today(), null, null, $this->owner);

        $this->assertSame(4, $this->gym->expenses()->count());
    }

    public function test_expense_validation_and_permissions(): void
    {
        $viewer = $this->employeeWithFinanceRole(['finance.expenses.view']);
        $manager = $this->employeeWithFinanceRole(['finance.expenses.manage']);
        $plain = $this->employeeWithFinanceRole([]);

        $payload = ['category' => 'rent', 'amount' => 1000, 'incurred_on' => '2026-11-01'];
        $this->actingAs($viewer)->getJson($this->url('finance/expenses'))->assertOk();
        $this->actingAs($viewer)->postJson($this->url('finance/expenses'), $payload)->assertForbidden();
        $this->actingAs($plain)->getJson($this->url('finance/expenses'))->assertForbidden();

        $created = $this->actingAs($manager)->postJson($this->url('finance/expenses'), $payload)
            ->assertCreated()->json('expense.id');
        $this->actingAs($viewer)
            ->postJson($this->url("finance/expenses/{$created}/correct"), ['reason' => 'Wrong'])
            ->assertForbidden();
        $this->actingAs($manager)
            ->postJson($this->url("finance/expenses/{$created}/correct"), ['reason' => 'Wrong'])
            ->assertCreated();

        $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'mystery', 'amount' => 10, 'incurred_on' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'rent', 'amount' => 0, 'incurred_on' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->actingAs($this->owner)->postJson($this->url('finance/expenses'), [
            'category' => 'rent', 'amount' => 10, 'incurred_on' => 'not-a-date',
        ])->assertUnprocessable()->assertJsonValidationErrors('incurred_on');
    }
}
