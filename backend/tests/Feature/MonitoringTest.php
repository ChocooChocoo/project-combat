<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymVisit;
use App\Support\CurrentGym;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

/** R-115 per-gym reminder days and R-116 inactivity notices. Notices only, nothing blocked. */
class MonitoringTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-20 08:00:00'));
        $this->setUpCustomerGym();
    }

    private function runCommand(string $command, string $output): void
    {
        $this->app->forgetScopedInstances();
        $this->artisan($command)->expectsOutput($output)->assertSuccessful();
    }

    public function test_inactive_member_is_told_once_and_staff_get_a_digest(): void
    {
        $this->membershipFor($this->member, ['status' => 'active', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);
        app(CurrentGym::class)->set($this->gym);
        GymVisit::create(['user_id' => $this->member->id, 'checked_in_at' => '2026-11-01 18:00:00', 'method' => 'desk']);

        $this->runCommand('app:monitor-inactivity', '1 inactive members noticed.');
        $this->runCommand('app:monitor-inactivity', '0 inactive members noticed.');

        $this->assertSame(1, $this->member->notifications()->count());
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertStringContainsString('Mia Torres', $this->owner->notifications()->first()->data['message']);

        // A new visit and a new quiet spell is told again.
        GymVisit::create(['user_id' => $this->member->id, 'checked_in_at' => '2026-11-02 18:00:00', 'method' => 'desk']);
        $this->runCommand('app:monitor-inactivity', '1 inactive members noticed.');
    }

    public function test_threshold_comes_from_gym_settings(): void
    {
        $this->membershipFor($this->member, ['status' => 'active', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31']);
        app(CurrentGym::class)->set($this->gym);
        GymVisit::create(['user_id' => $this->member->id, 'checked_in_at' => '2026-11-01 18:00:00', 'method' => 'desk']);

        $this->actingAs($this->owner)->putJson($this->url('setup'), ['inactivity_days' => 30])->assertOk();
        $this->runCommand('app:monitor-inactivity', '0 inactive members noticed.');
    }

    public function test_expiry_reminder_days_come_from_gym_settings(): void
    {
        $this->membershipFor($this->member, ['status' => 'active', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-05']);
        $this->runCommand('app:send-membership-reminders', '0 membership reminders sent.');

        Gym::whereKey($this->gym->id)->update(['membership_reminder_days' => json_encode([15])]);
        $this->runCommand('app:send-membership-reminders', '1 membership reminders sent.');
    }

    public function test_jobs_are_scheduled(): void
    {
        $names = collect(app(Schedule::class)->events())->map(fn ($event) => $event->description)->all();
        $this->assertContains('app:monitor-inactivity', $names);
    }
}
