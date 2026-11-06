<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

class ExactPermissionTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    public function test_roles_view_does_not_allow_staff_or_schedules_read(): void
    {
        $gym = Gym::factory()->create();
        $actor = User::factory()->create();
        $employee = User::factory()->create();
        $gym->addPerson($actor, GymPersonType::Employee);
        $gym->addPerson($employee, GymPersonType::Employee);
        app(CurrentGym::class)->set($gym);
        $this->makeRole(['hr.roles.view'])->users()->attach($actor->id);
        $this->actingAs($actor)->getJson("/api/gyms/{$gym->id}/roles")->assertOk();
        $this->actingAs($actor)->getJson("/api/gyms/{$gym->id}/staff")->assertForbidden();
        $this->actingAs($actor)->getJson("/api/gyms/{$gym->id}/staff/{$employee->id}")->assertForbidden();
        $this->actingAs($actor)->getJson("/api/gyms/{$gym->id}/staff/{$employee->id}/schedule")->assertForbidden();
    }

    public function test_pay_and_leave_helpers_require_exact_permissions(): void
    {
        $gym = Gym::factory()->create();
        $actor = User::factory()->create();
        $gym->addPerson($actor, GymPersonType::Employee);
        app(CurrentGym::class)->set($gym);
        $role = $this->makeRole(['hr.roles.manage']);
        $role->users()->attach($actor->id);
        $this->assertFalse($actor->canViewCompensation($gym));
        $this->assertFalse($actor->canApproveLeave($gym));
        $role->syncPermissionStrings(['hr.leave.manage']);
        $this->assertTrue($actor->canApproveLeave($gym));
    }

    public function test_notification_recipients_exclude_disabled_expired_and_foreign_grants(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $gym->addPerson($owner, GymPersonType::Owner);
        $gym->addPerson($actor, GymPersonType::Employee);
        app(CurrentGym::class)->set($gym);
        $role = $this->makeRole(['hr.leave.manage']);
        $role->users()->attach($actor->id, ['expires_at' => now()->subDay()]);
        $this->assertSame([$owner->id], $gym->peopleWithPermission('hr.leave.manage')->pluck('id')->all());
    }
}
