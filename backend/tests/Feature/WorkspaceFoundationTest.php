<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymHoliday;
use App\Models\GymRole;
use App\Models\OneTimeCode;
use App\Models\Room;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\EmployeeInvitation;
use App\Support\CurrentGym;
use App\Support\GymRoleAssigner;
use App\Support\OneTimeCodes;
use App\Support\WorkingDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Phase 3 shared foundation: workspace tables, working days, roles and invitations. */
class WorkspaceFoundationTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Concerns\GivesGymRoles;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        app(CurrentGym::class)->set($this->gym);
    }

    public function test_workspace_migrations_create_every_table_and_gym_columns(): void
    {
        $tables = [
            'rooms', 'employee_profiles', 'work_schedules', 'leave_requests', 'leave_balances', 'gym_holidays',
            'gym_services', 'gym_service_photos', 'membership_plans', 'membership_plan_addons', 'gym_preferences',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}.");
        }

        foreach (['activity_types', 'working_days', 'policies'] as $column) {
            $this->assertTrue(Schema::hasColumn('gyms', $column), "Missing gyms.{$column}.");
        }
    }

    public function test_rooms_count_only_active_ones_in_the_active_scope(): void
    {
        Room::factory()->forGym($this->gym)->create(['name' => 'Main Ring']);
        Room::factory()->forGym($this->gym)->create(['name' => 'Old Mat', 'is_active' => false]);

        $this->assertSame(2, Room::count());
        $this->assertSame(1, Room::active()->count());
        $this->assertSame('Main Ring', Room::active()->value('name'));
    }

    public function test_working_days_use_the_default_week_and_skip_national_and_gym_holidays(): void
    {
        $employee = User::factory()->create();
        $days = app(WorkingDays::class);
        $from = Carbon::parse('2026-11-02'); // Monday
        $to = Carbon::parse('2026-11-08'); // Sunday

        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $this->gym->workingDays());
        $this->assertSame(5, $days->count($this->gym, $employee, $from, $to));

        GymHoliday::factory()->forGym($this->gym)->create(['date' => '2026-11-04']);
        $this->assertSame(4, $days->count($this->gym, $employee, $from, $to));

        // 30 November 2026 is Bonifacio Day, a Monday.
        $this->assertSame(4, $days->count($this->gym, $employee, Carbon::parse('2026-11-30'), Carbon::parse('2026-12-04')));
    }

    public function test_an_employees_own_timed_schedule_wins_over_the_gym_week(): void
    {
        $employee = User::factory()->create();

        foreach (['tue', 'thu'] as $weekday) {
            WorkSchedule::factory()->forGym($this->gym)->create([
                'user_id' => $employee->id,
                'weekday' => $weekday,
            ]);
        }

        $this->assertSame(2, app(WorkingDays::class)->count(
            $this->gym, $employee, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-08'),
        ));
    }

    public function test_role_assigner_replaces_roles_and_blocks_escalation_and_self_change(): void
    {
        $owner = User::factory()->create();
        $hr = User::factory()->create();
        $colleague = User::factory()->create();
        $this->gym->addPerson($owner, GymPersonType::Owner);
        $this->gym->addPerson($hr, GymPersonType::Employee);
        $this->gym->addPerson($colleague, GymPersonType::Employee);

        $hrRole = $this->makeRole(['hr' => 'manage', 'finance' => 'view'], 'HR Officer');
        $hrRole->users()->attach($hr->id);

        $big = $this->makeRole(['finance' => 'approve'], 'Finance Head');
        $small = $this->makeRole(['finance' => 'view'], 'Cashier');

        $assigner = app(GymRoleAssigner::class);
        $assigner->replace($hr, $this->gym, $colleague, [$small->id]);

        $this->assertSame(['Cashier'], $colleague->gymRoles($this->gym)->pluck('name')->all());

        try {
            $assigner->replace($hr, $this->gym, $colleague, [$big->id]);
            $this->fail('A role above the actor access was assigned.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $assigner->replace($hr, $this->gym, $hr, []);
            $this->fail('The actor changed their own roles.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $outsider = User::factory()->create();

        try {
            $assigner->replace($hr, $this->gym, $outsider, []);
            $this->fail('Roles were changed for a person outside the gym.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_accept_invite_sets_the_password_and_the_code_works_once(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->forceFill(['password_change_required' => true])->save();
        $user->createToken('old');

        app(OneTimeCodes::class)->invite($user, $this->gym);

        $code = null;
        Notification::assertSentTo($user, EmployeeInvitation::class, function (EmployeeInvitation $notification) use (&$code, $user) {
            $code = $notification->code;

            return $notification->gym->is($this->gym) && $notification->email === $user->email;
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->postJson('/api/auth/accept-invite', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertOk()->assertJsonPath('message', 'Password set. You can sign in now.');

        $user->refresh();
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->password_change_required);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, OneTimeCode::where('user_id', $user->id)->where('purpose', OneTimeCodes::INVITE)->count());

        $this->postJson('/api/auth/accept-invite', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }
}
