<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportAccessTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_owner_gets_server_manila_defaults_and_configured_thresholds(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl())
            ->assertOk()->assertExactJson([
                'today' => '2026-11-07', 'timezone' => 'Asia/Manila',
                'default_range' => ['from' => '2026-11-01', 'to' => '2026-11-07'],
                'views' => ['attendance', 'memberships', 'athletes', 'services', 'customers', 'employees',
                    'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'],
                'thresholds' => [
                    'trends' => ['months' => 12],
                    'forecast' => ['history_months' => 12, 'ahead_months' => 3, 'minimum_trend_months' => 3],
                    'falling_attendance' => ['window_sessions' => 4, 'drop_points' => 20, 'current_below' => 60],
                    'low_use_slot' => ['minimum_sessions' => 3, 'fill_below' => 25],
                    'high_no_show' => ['minimum_expected' => 5, 'rate_at_least' => 30],
                ],
            ]);
    }

    public function test_decision_only_employee_needs_no_source_module_permission(): void
    {
        $employee = $this->employeeWithFinanceRole(['decision_support.general.view']);
        $this->actingAs($employee)->getJson($this->decisionUrl())->assertOk();
    }

    public function test_plain_employee_payroll_only_and_member_are_denied(): void
    {
        $plain = User::factory()->create();
        $this->gym->addPerson($plain, GymPersonType::Employee);
        $payroll = $this->employeeWithFinanceRole(['payroll.runs.view']);
        foreach ([$plain, $payroll, $this->member] as $user) {
            $this->actingAs($user)->getJson($this->decisionUrl())->assertForbidden();
        }
    }

    public function test_unauthenticated_foreign_gym_and_admin_without_role_are_denied(): void
    {
        foreach ($this->allDecisionUrls() as $url) $this->getJson($url)->assertUnauthorized();
        $other = Gym::factory()->approved()->create();
        foreach ($this->allDecisionUrls() as $url) {
            $this->actingAs($this->owner)->getJson(str_replace("/gyms/{$this->gym->id}/", "/gyms/{$other->id}/", $url))->assertNotFound();
        }
        $admin = User::factory()->create(['is_platform_admin' => true]);
        foreach ($this->allDecisionUrls() as $url) $this->actingAs($admin)->getJson($url)->assertNotFound();
    }

    public function test_disabled_catalog_permission_denies_even_owner(): void
    {
        DB::table('permissions')->where('permission_string', 'decision_support.general.view')->update(['status' => 'inactive']);
        $this->app->forgetScopedInstances();
        foreach ($this->allDecisionUrls() as $url) $this->actingAs($this->owner)->getJson($url)->assertForbidden();
    }

    public function test_inactive_and_expired_role_grants_are_denied(): void
    {
        $employee = $this->employeeWithFinanceRole(['decision_support.general.view']);
        $role = $employee->gymRoles($this->gym)->firstOrFail();
        $role->update(['status' => 'inactive']);
        $this->app->forgetScopedInstances();
        foreach ($this->allDecisionUrls() as $url) $this->actingAs($employee)->getJson($url)->assertForbidden();
        $role->update(['status' => 'active']);
        $role->users()->updateExistingPivot($employee->id, ['expires_at' => now()->subSecond()]);
        $this->app->forgetScopedInstances();
        foreach ($this->allDecisionUrls() as $url) $this->actingAs($employee)->getJson($url)->assertForbidden();
    }

    public function test_implemented_views_require_exact_permission_and_valid_dates(): void
    {
        $decision = $this->employeeWithFinanceRole(['decision_support.general.view']);
        $payroll = $this->employeeWithFinanceRole(['payroll.runs.view']);
        $plain = $this->employeeWithFinanceRole(['hr.staff.manage', 'finance.payments.view']);
        foreach (['attendance', 'athletes', 'services', 'memberships', 'customers', 'employees', 'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'] as $view) {
            $url = $this->decisionUrl($view);
            foreach ([$this->owner, $decision] as $user) {
                $this->actingAs($user)->getJson($url.'?from=2026-11-02&to=2026-11-02')->assertOk();
                $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
                $this->getJson($url.'?from=2026-02-30&to=2026-11-02')->assertUnprocessable()->assertJsonValidationErrors('from');
                $this->getJson($url.'?from=2026-11-03&to=2026-11-02')->assertUnprocessable()->assertJsonValidationErrors('to');
            }
            foreach ([$plain, $payroll, $this->member] as $user) {
                $this->actingAs($user)->getJson($url.'?from=2026-11-02&to=2026-11-02')->assertForbidden();
            }
        }
    }

    private function allDecisionUrls(): array
    {
        return array_map(fn ($view) => $this->decisionUrl($view).'?from=2026-11-01&to=2026-11-07', [
            'options', 'attendance', 'memberships', 'athletes', 'services', 'customers', 'employees',
            'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability',
        ]);
    }
}
