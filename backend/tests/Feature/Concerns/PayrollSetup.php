<?php

namespace Tests\Feature\Concerns;

use App\Enums\GymPersonType;
use App\Models\EmployeeProfile;
use App\Models\Gym;
use App\Models\PayrollRun;
use App\Models\StaffAttendance;
use App\Models\StatutoryRate;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\CurrentGym;
use Illuminate\Support\Carbon;

/** Shared fixtures for the Packet 6F payroll tests. */
trait PayrollSetup
{
    use GivesGymRoles;

    protected Gym $gym;

    protected User $owner;

    protected User $hr;

    protected User $employee;

    protected function setUpPayrollGym(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 1)->startOfDay());

        $this->gym = Gym::factory()->approved()->create([
            'payroll_frequency' => 'semi_monthly',
            'payroll_steps' => ['owner'],
            'staff_grace_minutes' => 10,
            'overtime_min_minutes' => 30,
        ]);

        $this->owner = User::factory()->create(['first_name' => 'Owen', 'last_name' => 'Reyes']);
        $this->hr = User::factory()->create(['first_name' => 'Hana', 'last_name' => 'Cruz']);
        $this->employee = User::factory()->create(['first_name' => 'Ella', 'last_name' => 'Santos']);

        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->hr, GymPersonType::Employee);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);

        $this->givePayrollRole($this->hr, [
            'payroll.runs.view', 'payroll.runs.manage', 'payroll.runs.approve', 'payroll.rates.manage',
            'hr.attendance.view', 'hr.attendance.manage', 'hr.overtime.approve', 'hr.leave.manage',
            'hr.holidays.manage',
        ], 'Payroll HR');

        $this->seedPayrollRates();
    }

    protected function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    protected function givePayrollRole(User $user, array $permissions, string $name = 'Payroll role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeRole($permissions, $name.' '.$user->id)->users()->attach($user->id);
        $this->app->forgetScopedInstances();
        app(CurrentGym::class)->set($this->gym);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function payrollProfile(User $user, array $overrides = []): EmployeeProfile
    {
        app(CurrentGym::class)->set($this->gym);

        $profile = new EmployeeProfile;
        $profile->forceFill([
            'gym_id' => $this->gym->id,
            'user_id' => $user->id,
            'job_title' => 'Coach',
            'started_on' => '2026-01-01',
            'employment_type' => 'employee',
            'pay_basis' => 'monthly',
            'monthly_rate' => '30000.00',
            'per_session_rate' => '500.00',
            ...$overrides,
        ])->save();

        return $profile;
    }

    /** @param  list<string>  $weekdays */
    protected function payrollSchedule(User $user, array $weekdays = ['mon', 'tue', 'wed', 'thu', 'fri'], string $start = '09:00', string $end = '17:00'): void
    {
        app(CurrentGym::class)->set($this->gym);

        foreach ($weekdays as $weekday) {
            WorkSchedule::create([
                'user_id' => $user->id,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }
    }

    protected function payrollRun(string $from = '2026-11-01', string $to = '2026-11-15', ?User $preparedBy = null): PayrollRun
    {
        app(CurrentGym::class)->set($this->gym);

        return $this->gym->payrollRuns()->create([
            'starts_on' => $from,
            'ends_on' => $to,
            'status' => 'draft',
            'round' => 1,
            'current_step' => 0,
            'prepared_by' => ($preparedBy ?? $this->hr)->id,
        ]);
    }

    protected function markPresent(User $user, string $from = '2026-11-01', string $to = '2026-11-15'): void
    {
        app(CurrentGym::class)->set($this->gym);

        $day = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($day->lessThanOrEqualTo($end)) {
            if (! in_array(strtolower($day->format('D')), ['sat', 'sun'], true)) {
                $exists = $this->gym->staffAttendance()
                    ->where('user_id', $user->id)
                    ->whereDate('work_date', $day->toDateString())
                    ->exists();

                if (! $exists) {
                    $start = Carbon::parse($day->toDateString().' 09:00');
                    $finish = Carbon::parse($day->toDateString().' 17:00');

                    $this->gym->staffAttendance()->create([
                        'user_id' => $user->id,
                        'work_date' => $day->toDateString(),
                        'scheduled_start' => '09:00',
                        'scheduled_end' => '17:00',
                        'clock_in_at' => $start,
                        'clock_out_at' => $finish,
                        'status' => 'present',
                        'late_minutes' => 0,
                        'undertime_minutes' => 0,
                        'worked_minutes' => 480,
                        'source' => 'desk',
                        'recorded_by' => $this->owner->id,
                    ]);
                }
            }

            $day->addDay();
        }
    }

    protected function seedPayrollRates(string $effectiveFrom = '2025-01-01'): void
    {
        $rates = [
            ['kind' => 'sss', 'brackets' => [
                ['min' => '0.00', 'max' => null, 'employee' => '100.00', 'employer' => '190.00', 'ec' => '10.00'],
            ]],
            ['kind' => 'philhealth', 'brackets' => [
                ['min' => '0.00', 'max' => null, 'employee_rate' => 0.025, 'employer_rate' => 0.025],
            ]],
            ['kind' => 'pagibig', 'brackets' => [
                ['min' => '0.00', 'max' => null, 'employee_rate' => 0.02, 'employer_rate' => 0.02],
            ]],
            ['kind' => 'tax_semi_monthly', 'brackets' => [
                ['min' => '0.00', 'max' => null, 'base' => '0.00', 'rate' => 0.0],
            ]],
            ['kind' => 'tax_monthly', 'brackets' => [
                ['min' => '0.00', 'max' => null, 'base' => '0.00', 'rate' => 0.0],
            ]],
        ];

        foreach ($rates as $rate) {
            StatutoryRate::create([
                'gym_id' => null,
                'kind' => $rate['kind'],
                'effective_from' => $effectiveFrom,
                'brackets' => $rate['brackets'],
                'source' => 'Test statutory table',
                'created_by' => null,
            ]);
        }
    }

    protected function statutoryRate(string $kind, string $effectiveFrom, array $brackets, string $source = 'Test override'): StatutoryRate
    {
        return StatutoryRate::create([
            'gym_id' => null,
            'kind' => $kind,
            'effective_from' => $effectiveFrom,
            'brackets' => $brackets,
            'source' => $source,
            'created_by' => null,
        ]);
    }

    protected function payrollAttendance(User $user, string $date): StaffAttendance
    {
        app(CurrentGym::class)->set($this->gym);

        return $this->gym->staffAttendance()->where('user_id', $user->id)->whereDate('work_date', $date)->firstOrFail();
    }
}
