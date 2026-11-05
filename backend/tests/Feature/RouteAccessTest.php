<?php

namespace Tests\Feature;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    public function test_every_route_has_the_exact_resource_permission(): void
    {
        $expected = [
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
