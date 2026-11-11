<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-20: the workspace setup screen, its options, and its permissions. */
class WorkspaceSetupTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Concerns\GivesGymRoles;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->approved(1)->create(['name' => 'Old Name']);
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);

        app(CurrentGym::class)->set($this->gym);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function give(User $user, array $permissions, string $name = 'Test role'): void
    {
        app(CurrentGym::class)->set($this->gym);
        $role = $this->makeRole($permissions, $name);
        $role->users()->attach($user->id);
        $this->app->forgetScopedInstances();
    }

    public function test_owner_updates_the_workspace_setup(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'name' => 'Iron Works',
            'address_line' => '1 Main Street',
            'barangay' => 'Burol',
            'city' => 'Dasmariñas',
            'province' => 'Cavite',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'contact_phone' => '09171234567',
            'contact_email' => 'hello@example.com',
            'disciplines' => ['boxing', 'bjj'],
            'activity_types' => ['classes', 'open_mat'],
            'operating_hours' => [
                'mon' => ['open' => '06:00', 'close' => '21:00'],
                'sun' => null,
            ],
            'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'policies' => 'No shoes on the mat.',
            // These are owned by the application, not the workspace settings.
            'status' => 'rejected',
            'tier' => 9,
            'applicant_id' => 999,
        ])->assertOk()
            ->assertJsonPath('setup.name', 'Iron Works')
            ->assertJsonPath('setup.disciplines', ['boxing', 'bjj'])
            ->assertJsonPath('setup.activity_types', ['classes', 'open_mat'])
            ->assertJsonPath('setup.operating_hours.mon.open', '06:00')
            ->assertJsonPath('setup.operating_hours.sun', null)
            ->assertJsonPath('setup.working_days', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])
            ->assertJsonPath('setup.policies', 'No shoes on the mat.')
            ->assertJsonPath('setup.profile.city', 'Dasmariñas')
            ->assertJsonPath('setup.options.disciplines.boxing', 'Boxing');

        $fresh = $this->gym->fresh();
        $this->assertSame('Iron Works', $fresh->name);
        $this->assertSame(GymStatus::Approved, $fresh->status);
        $this->assertSame(1, $fresh->tier);
        $this->assertNull($fresh->applicant_id);
    }

    public function test_setup_shows_the_tier_badge_and_the_default_working_week(): void
    {
        $this->actingAs($this->owner)->getJson($this->url('setup'))
            ->assertOk()
            ->assertJsonPath('setup.badge', 'Tier 1 verified')
            ->assertJsonPath('setup.tier', 1)
            ->assertJsonPath('setup.working_days', ['mon', 'tue', 'wed', 'thu', 'fri'])
            ->assertJsonPath('setup.payroll_frequency', 'semi_monthly')
            ->assertJsonPath('setup.payroll_steps', ['owner'])
            ->assertJsonPath('setup.staff_grace_minutes', 10)
            ->assertJsonPath('setup.overtime_min_minutes', 30)
            ->assertJsonPath('setup.options.default_working_days', ['mon', 'tue', 'wed', 'thu', 'fri'])
            ->assertJsonPath('setup.options.plan_billing.sessions', 'By sessions');
    }

    public function test_setup_ignores_payroll_and_attendance_settings(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'payroll_frequency' => 'monthly',
            'payroll_steps' => ['hr_review', 'owner'],
            'staff_grace_minutes' => 15,
            'overtime_min_minutes' => 45,
        ])->assertOk()
            ->assertJsonPath('setup.payroll_frequency', 'semi_monthly')
            ->assertJsonPath('setup.payroll_steps', ['owner'])
            ->assertJsonPath('setup.staff_grace_minutes', 10)
            ->assertJsonPath('setup.overtime_min_minutes', 30)
            ->assertJsonPath('setup.options.payroll_frequencies.monthly', 'Once a month')
            ->assertJsonPath('setup.options.payroll_steps.owner', 'Owner');
    }

    public function test_options_are_open_to_every_member_but_setup_needs_permission(): void
    {
        $this->actingAs($this->employee)->getJson($this->url('options'))
            ->assertOk()
            ->assertJsonPath('options.weekdays.mon', 'Monday')
            ->assertJsonPath('options.pay_bases.both', 'Monthly and per session')
            ->assertJsonPath('options.leave_types.vacation.days', 15)
            ->assertJsonPath('options.attachment.max_kb', 5120)
            ->assertJsonPath('options.service_photos.max', 5);

        $this->actingAs($this->employee)->getJson($this->url('setup'))->assertForbidden();
        $this->actingAs($this->employee)->putJson($this->url('setup'), ['name' => 'Nope'])->assertForbidden();

        $this->give($this->employee, ['gym_setup' => 'view'], 'Setup viewer');

        $this->actingAs($this->employee)->getJson($this->url('setup'))->assertOk();
        $this->actingAs($this->employee)->putJson($this->url('setup'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_setup_rejects_unknown_choices_and_backwards_hours(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'disciplines' => ['fencing'],
            'activity_types' => ['bogus'],
            'working_days' => ['funday'],
            'operating_hours' => ['mon' => ['open' => '20:00', 'close' => '08:00']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'disciplines.0',
                'activity_types.0',
                'working_days.0',
                'operating_hours.mon.close',
            ]);
    }

    public function test_setup_accepts_a_midnight_close(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'operating_hours' => ['mon' => ['open' => '08:00', 'close' => '00:00'], 'tue' => ['open' => '00:00', 'close' => '00:00']],
        ])->assertOk();
    }

    public function test_location_is_limited_to_cavite_with_server_side_zip(): void
    {
        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'city' => 'Makati', 'barangay' => 'Poblacion', 'province' => 'Metro Manila',
        ])->assertUnprocessable()->assertJsonValidationErrors(['city']);

        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'city' => 'Dasmariñas', 'barangay' => 'Alima',
        ])->assertUnprocessable()->assertJsonValidationErrors(['barangay']);

        $this->actingAs($this->owner)->putJson($this->url('setup'), [
            'city' => 'Dasmariñas', 'barangay' => 'Burol', 'province' => 'Cebu', 'zip_code' => '9999',
        ])->assertOk()
            ->assertJsonPath('setup.profile.province', 'Cavite')
            ->assertJsonPath('setup.profile.zip_code', '4114');
    }

    public function test_cavite_locations_endpoint_lists_all_municipalities(): void
    {
        $r = $this->actingAs($this->owner)->getJson('/api/locations/cavite')->assertOk()
            ->assertJsonPath('province', 'Cavite')
            ->assertJsonCount(23, 'municipalities');

        $dasma = collect($r->json('municipalities'))->firstWhere('name', 'Dasmariñas');
        $this->assertCount(75, $dasma['barangays']);
        $this->assertSame('4114', $dasma['zip']);
    }
}
