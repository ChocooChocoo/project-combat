<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;

/** A gym, an owner, one employee and the timetable used by the attendance tests. */
trait AttendanceSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $employee;

    protected function setUpAttendanceGym(): void
    {
        $this->gym = Gym::factory()->approved()->create();
        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->employee = User::factory()->create(['first_name' => 'Ella', 'last_name' => 'Santos']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function scheduleFor(User $user, string $weekday = 'thu', string $start = '09:00', string $end = '17:00'): void
    {
        WorkSchedule::create([
            'user_id' => $user->id,
            'weekday' => $weekday,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    protected function profileFor(User $user, array $changes = []): EmployeeProfile
    {
        $profile = new EmployeeProfile;
        $profile->forceFill([
            'user_id' => $user->id,
            'job_title' => 'Coach',
            'started_on' => '2026-01-01',
            ...$changes,
        ])->save();

        return $profile;
    }

    protected function giveRole(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }
}
