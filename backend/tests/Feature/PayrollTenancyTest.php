<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\PayrollLine;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PayrollSetup;
use Tests\TestCase;

/** R-09 and R-59: one gym's payroll never appears in another gym. */
class PayrollTenancyTest extends TestCase
{
    use PayrollSetup, RefreshDatabase;

    private Gym $otherGym;

    private User $otherOwner;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayrollGym();
        $this->payrollProfile($this->employee);
        $this->markPresent($this->employee);

        $this->otherGym = Gym::factory()->approved()->create();
        $this->otherOwner = User::factory()->create(['first_name' => 'Olga', 'last_name' => 'Bautista']);
        $this->otherEmployee = User::factory()->create(['first_name' => 'Nico', 'last_name' => 'Flores']);
        $this->otherGym->addPerson($this->otherOwner, GymPersonType::Owner);
        $this->otherGym->addPerson($this->otherEmployee, GymPersonType::Employee);
    }

    public function test_another_gyms_run_line_and_rate_are_not_reachable(): void
    {
        $run = $this->payrollRun(preparedBy: $this->owner);
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/compute"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/submit"))->assertOk();
        $this->actingAs($this->owner)->postJson($this->url("payroll/runs/{$run->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk()->assertJsonPath('run.status', 'released');

        $line = PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->firstOrFail();

        $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/payroll/runs/{$run->id}")
            ->assertNotFound();

        $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/payroll/runs")
            ->assertOk()
            ->assertJsonCount(0, 'runs');

        $this->actingAs($this->otherEmployee)->getJson("/api/me/payslips/{$line->id}")->assertNotFound();
        $this->actingAs($this->otherEmployee)->getJson('/api/me/payslips')->assertOk()->assertJsonCount(0, 'payslips');

        $this->actingAs($this->owner)->postJson($this->url('payroll/rates'), [
            'kind' => 'sss',
            'effective_from' => '2026-12-01',
            'source' => 'Gym A only',
            'brackets' => [
                ['min' => '0.00', 'max' => null, 'employee' => '500.00', 'employer' => '900.00', 'ec' => '10.00'],
            ],
        ])->assertCreated()->assertJsonPath('rate.source', 'Gym A only');

        $mine = $this->actingAs($this->owner)->getJson($this->url('payroll/rates'))->assertOk();
        $theirs = $this->actingAs($this->otherOwner)
            ->getJson("/api/gyms/{$this->otherGym->id}/payroll/rates")
            ->assertOk();

        $this->assertTrue(collect($mine->json('rates'))->contains(fn (array $rate) => $rate['source'] === 'Gym A only'));
        $this->assertFalse(collect($theirs->json('rates'))->contains(fn (array $rate) => $rate['source'] === 'Gym A only'));

        app(CurrentGym::class)->set($this->gym);
        $this->assertSame(1, PayrollLine::withoutGlobalScope('gym')->where('payroll_run_id', $run->id)->count());
    }
}
