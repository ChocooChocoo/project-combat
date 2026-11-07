<?php

namespace Tests\Feature;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    public function test_platform_notices_use_admin_middleware_not_gym_permissions(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/admin/notices', $method));
            $this->assertContains('admin', $route->gatherMiddleware());
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(),
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'permission:'))));
        }
    }

    public function test_every_route_has_the_exact_resource_permission(): void
    {
        $expected = [
            'GET decision-support/options' => 'decision_support.general.view',
            'GET decision-support/attendance' => 'decision_support.general.view',
            'GET decision-support/athletes' => 'decision_support.general.view',
            'GET decision-support/services' => 'decision_support.general.view',
            'GET decision-support/memberships' => 'decision_support.general.view',
            'GET decision-support/customers' => 'decision_support.general.view',
            'GET decision-support/employees' => 'decision_support.general.view',
            'GET decision-support/money' => 'decision_support.general.view',
            'GET decision-support/stock' => 'decision_support.general.view',
            'GET decision-support/trends' => 'decision_support.general.view',
            'GET decision-support/forecast' => 'decision_support.general.view',
            'GET decision-support/problems' => 'decision_support.general.view',
            'GET decision-support/sustainability' => 'decision_support.general.view',
            'GET announcements' => 'announcements.general.view',
            'GET announcements/options' => 'announcements.general.manage',
            'POST announcements' => 'announcements.general.manage',
            'GET roles' => 'hr.roles.view', 'GET staff/{user}/roles' => 'hr.roles.view',
            'POST roles' => 'hr.roles.manage', 'PUT roles/{role}' => 'hr.roles.manage',
            'DELETE roles/{role}' => 'hr.roles.manage', 'PUT staff/{user}/roles' => 'hr.roles.manage',
            'POST roles/{role}/toggle-status' => 'hr.roles.manage',
            'GET setup' => 'gym_setup.settings.view', 'PUT setup' => 'gym_setup.settings.manage',
            'GET rooms' => 'gym_setup.rooms.view', 'POST rooms' => 'gym_setup.rooms.manage',
            'PUT rooms/{room}' => 'gym_setup.rooms.manage', 'DELETE rooms/{room}' => 'gym_setup.rooms.manage',
            'GET check-ins/members' => 'training.check_ins.manage', 'GET check-ins' => 'training.check_ins.manage',
            'POST check-ins' => 'training.check_ins.manage', 'GET attendance' => 'training.attendance.view',
            'PUT bookings/{booking}/attendance' => 'training.attendance.manage',
            'POST bookings/{booking}/check-out' => 'training.attendance.manage',
            'GET bookings' => 'training.bookings.view', 'POST bookings/{booking}/decision' => 'training.bookings.approve',
            'GET sessions/{session}/roster' => 'training.sessions.view',
            'GET sessions/options' => 'training.sessions.view', 'GET sessions' => 'training.sessions.view',
            'GET sessions/{session}' => 'training.sessions.view', 'POST sessions' => 'training.sessions.manage',
            'PUT sessions/{session}' => 'training.sessions.manage', 'POST sessions/{session}/cancel' => 'training.sessions.manage',
            'GET sessions/{session}/free-coaches' => 'training.sessions.view',
            'GET staff' => 'hr.staff.view', 'POST staff' => 'hr.staff.manage', 'PUT staff/{user}' => 'hr.staff.manage',
            'DELETE staff/{user}' => 'hr.staff.manage', 'PUT staff/{user}/schedule' => 'hr.schedules.manage',
            'POST leave-requests/{leave}/decision' => 'hr.leave.manage',
            'POST holidays' => 'hr.holidays.manage', 'DELETE holidays/{holiday}' => 'hr.holidays.manage',
            'GET plans' => 'members.plans.view', 'POST plans' => 'members.plans.manage',
            'PUT plans/{plan}' => 'members.plans.manage', 'DELETE plans/{plan}' => 'members.plans.manage',
            'POST plans/{plan}/publish' => 'members.plans.manage', 'POST plans/{plan}/unpublish' => 'members.plans.manage',
            'GET memberships/{membership}' => 'members.memberships.view',
            'POST memberships/{membership}/decision' => 'members.memberships.approve',
            'POST memberships/{membership}/renew' => 'members.memberships.manage',
            'POST memberships/{membership}/freeze' => 'members.memberships.manage',
            'POST memberships/{membership}/unfreeze' => 'members.memberships.manage',
            'POST memberships/{membership}/cancel' => 'members.memberships.manage',
            'GET payments' => 'finance.payments.view', 'GET payments/owed-bookings' => 'finance.payments.view',
            'POST payments' => 'finance.payments.manage', 'POST payments/{payment}/correct' => 'finance.payments.manage',
            'POST athletes/{user}/entries' => 'training.athletes.manage',
            'GET stock-items' => 'inventory.items.view',
            'GET stock-items/recipients' => 'inventory.movements.manage',
            'POST stock-items' => 'inventory.items.manage',
            'GET stock-items/{item}' => 'inventory.items.view',
            'PUT stock-items/{item}' => 'inventory.items.manage',
            'POST stock-items/{item}/archive' => 'inventory.items.manage',
            'GET stock-items/{item}/movements' => 'inventory.items.view',
            'POST stock-items/{item}/movements' => 'inventory.movements.manage',
            'GET customers/linkable-memberships' => 'customers.records.view',
            'GET customers' => 'customers.records.view', 'GET customers/{customer}' => 'customers.records.view',
            'POST customers' => 'customers.records.manage', 'PUT customers/{customer}' => 'customers.records.manage',
            'POST customers/{customer}/interactions' => 'customers.records.manage',
            'POST interactions/{interaction}/resolve' => 'customers.records.manage',
            'GET follow-ups' => 'customers.records.view',
            'POST customers/{customer}/follow-ups' => 'customers.records.manage',
            'PUT follow-ups/{followUp}' => 'customers.records.manage',
            'POST customers/{customer}/link-membership' => 'customers.records.manage',
            'GET customer-groups' => 'customers.records.view',
            'POST customer-groups' => 'customers.groups.manage',
            'PUT customer-groups/{group}' => 'customers.groups.manage',
            'DELETE customer-groups/{group}' => 'customers.groups.manage',
            'PUT customer-groups/{group}/members' => 'customers.groups.manage',
            'GET restock' => 'procurement.requests.view,inventory.items.view',
            'GET supply-needs' => 'procurement.requests.view',
            'POST supply-needs' => 'procurement.requests.manage',
            'DELETE supply-needs/{need}' => 'procurement.requests.manage',
            'GET purchase-requests' => 'procurement.requests.view',
            'POST purchase-requests' => 'procurement.requests.manage',
            'POST purchase-requests/{purchaseRequest}/decision' => 'procurement.requests.approve',
            'POST purchase-requests/{purchaseRequest}/cancel' => 'procurement.requests.manage',
            'GET suppliers' => 'procurement.suppliers.view',
            'POST suppliers' => 'procurement.suppliers.manage',
            'PUT suppliers/{supplier}' => 'procurement.suppliers.manage',
            'GET purchase-orders' => 'procurement.orders.view',
            'GET purchase-orders/{order}' => 'procurement.orders.view',
            'POST purchase-orders' => 'procurement.orders.manage',
            'POST purchase-orders/direct' => 'procurement.orders.manage',
            'POST purchase-orders/{order}/cancel' => 'procurement.orders.manage',
            'POST purchase-orders/{order}/close' => 'procurement.orders.manage',
            'POST purchase-orders/{order}/receipts' => 'procurement.receipts.manage',
            'GET finance/payables' => 'finance.payables.view',
            'GET finance/payables/{supplier}' => 'finance.payables.view',
            'POST finance/supplier-payments' => 'finance.payables.manage',
            'GET payroll/runs' => 'payroll.runs.view',
            'POST payroll/runs' => 'payroll.runs.manage',
            'GET payroll/runs/{run}' => 'payroll.runs.view',
            'POST payroll/runs/{run}/compute' => 'payroll.runs.manage',
            'POST payroll/runs/{run}/submit' => 'payroll.runs.manage',
            'POST payroll/runs/{run}/decision' => 'payroll.runs.approve',
            'DELETE payroll/runs/{run}' => 'payroll.runs.manage',
            'GET payroll/settings' => 'payroll.runs.view',
            'GET payroll/rates' => 'payroll.runs.view',
            'POST payroll/rates' => 'payroll.rates.manage',
            'GET finance/budgets' => 'finance.budgets.view',
            'PUT finance/budgets' => 'finance.budgets.manage',
            'GET finance/reports/cash-flow' => 'finance.reports.view',
            'GET finance/reports/income-expense' => 'finance.reports.view',
            'GET finance/reports/payroll' => 'finance.reports.view',
            'GET finance/reports/payouts' => 'finance.reports.view',
        ];
        foreach (['POST services', 'PUT services/{service}', 'DELETE services/{service}', 'POST services/{service}/cover',
            'POST services/{service}/photos', 'DELETE services/{service}/photos/{photo}',
            'POST services/{service}/publish', 'POST services/{service}/unpublish'] as $route) {
            $expected[$route] = 'gym_setup.services.manage';
        }
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }
                $this->assertStringStartsNotWith('module:', $middleware, $route->uri());
                if (str_starts_with($middleware, 'permission:')) {
                    foreach (explode(',', substr($middleware, 11)) as $permission) {
                        $this->assertTrue(PermissionCatalog::exists($permission), $route->uri());
                    }
                    $key = $route->methods()[0].' '.str_replace('api/gyms/{gym}/', '', $route->uri());
                    $actual[$key] = substr($middleware, 11);
                }
            }
        }
        foreach ($expected as $key => $permission) {
            $this->assertSame($permission, $actual[$key] ?? null, $key);
        }
    }
}
