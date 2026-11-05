<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\GymRoleAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_is_exact_active_unexpired_and_gym_scoped(): void
    {
        $gym = Gym::factory()->create();
        $other = Gym::factory()->create();
        $user = User::factory()->create();
        $gym->addPerson($user, GymPersonType::Employee);
        $other->addPerson($user, GymPersonType::Employee);
        app(CurrentGym::class)->set($gym);
        $role = GymRole::create(['name' => 'Approver']);
        $role->syncPermissionStrings(['training.bookings.approve']);
        $role->users()->attach($user->id);
        $this->assertTrue($user->hasPermission($gym, 'training.bookings.approve'));
        $this->assertFalse($user->hasPermission($gym, 'training.bookings.view', 'training.sessions.manage'));
        $this->assertFalse($user->hasPermission($other, 'training.bookings.approve'));
        $role->update(['status' => 'inactive']);
        $this->assertFalse($user->hasPermission($gym, 'training.bookings.approve'));
        $role->update(['status' => 'active']);
        $role->users()->updateExistingPivot($user->id, ['expires_at' => now()->subSecond()]);
        $this->assertFalse($user->hasPermission($gym, 'training.bookings.approve'));
        $role->users()->updateExistingPivot($user->id, ['expires_at' => now()->addSecond()]);
        Permission::where('permission_string', 'training.bookings.approve')->update(['status' => 'inactive']);
        $this->assertFalse($user->hasPermission($gym, 'training.bookings.approve'));
    }

    public function test_assignment_rolls_back_detachment_on_partial_failure(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $gym->addPerson($owner, GymPersonType::Owner);
        $gym->addPerson($employee, GymPersonType::Employee);
        app(CurrentGym::class)->set($gym);
        $old = GymRole::create(['name' => 'Old']);
        $new = GymRole::create(['name' => 'New']);
        $old->users()->attach($employee->id);
        DB::unprepared('CREATE TRIGGER reject_new_grant BEFORE INSERT ON gym_role_user WHEN NEW.gym_role_id = '.$new->id." BEGIN SELECT RAISE(ABORT, 'forced partial failure'); END");
        try {
            app(GymRoleAssigner::class)->replace($owner, $gym, $employee, [$new->id]);
            $this->fail('Expected insert failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('forced partial failure', $exception->getMessage());
        }
        $this->assertTrue($old->users()->whereKey($employee->id)->exists());
        $this->assertFalse($new->users()->whereKey($employee->id)->exists());
    }

    public function test_unknown_sync_keys_fail_without_discarding_existing_permissions(): void
    {
        app(CurrentGym::class)->set(Gym::factory()->create());
        $role = GymRole::create(['name' => 'Role']);
        $role->syncPermissionStrings(['finance.payments.view']);
        try {
            $role->syncPermissionStrings(['finance.payments.typo']);
            $this->fail('Unknown key was accepted.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(['finance.payments.view'], $role->permissionStrings());
        }
    }
}
