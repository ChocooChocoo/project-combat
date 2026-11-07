<?php

namespace Tests\Feature;

use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

class MembershipExpiryReminderTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 08:00:00'));
        $this->setUpCustomerGym();
        $this->membership = $this->membershipFor($this->member, ['status' => 'active', 'ends_on' => '2026-11-16']);
    }

    private function runReminders(int $expected): void
    {
        $this->app->forgetScopedInstances();
        $this->artisan('app:send-membership-reminders')->expectsOutput("{$expected} membership reminders sent.")->assertSuccessful();
    }

    public function test_an_active_membership_gets_one_reminder_at_each_threshold(): void
    {
        foreach (['2026-11-09', '2026-11-13', '2026-11-15'] as $date) {
            $this->travelTo(Carbon::parse($date.' 08:00:00'));
            $this->runReminders(1);
            $this->runReminders(0);
        }
        $this->assertDatabaseCount('membership_reminders', 3);
        $this->assertSame(3, $this->member->notifications()->count());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_a_missed_threshold_catches_up_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00'));
        $this->runReminders(1);
        $this->runReminders(0);
        $notice = $this->member->notifications()->sole()->data;
        $this->assertSame(5, $notice['days_left']);
        $this->assertStringContainsString('5 days', $notice['message']);
        $this->assertStringContainsString($this->plan->name, $notice['message']);
        $this->assertSame("/memberships/{$this->membership->id}", $notice['url']);
        $this->assertDatabaseHas('membership_reminders', ['membership_id' => $this->membership->id, 'days_before' => 7]);
        $this->travelTo(Carbon::parse('2026-11-14 08:00:00'));
        $this->runReminders(1);
        $this->assertDatabaseHas('membership_reminders', ['membership_id' => $this->membership->id, 'days_before' => 3]);
    }

    public function test_a_second_run_on_the_same_day_sends_nothing(): void
    {
        $this->runReminders(1);
        $this->runReminders(0);
        $this->assertSame(1, $this->member->notifications()->count());
    }

    public function test_frozen_memberships_and_session_plans_without_an_end_date_are_skipped(): void
    {
        $this->membership->forceFill(['status' => 'frozen'])->save();
        $this->membershipFor($this->employee, ['status' => 'active', 'ends_on' => null, 'sessions_remaining' => 10]);
        $this->runReminders(0);
        $this->assertDatabaseCount('membership_reminders', 0);
    }

    public function test_a_renewed_end_date_starts_a_new_reminder_cycle(): void
    {
        $this->runReminders(1);
        $this->membership->forceFill(['ends_on' => '2026-11-18'])->save();
        $this->runReminders(0);
        $this->travelTo(Carbon::parse('2026-11-11 08:00:00'));
        $this->runReminders(1);
        $this->assertDatabaseCount('membership_reminders', 2);
    }

    public function test_only_active_memberships_in_approved_gyms_are_reminded(): void
    {
        foreach (['applied', 'expired', 'cancelled'] as $status) {
            $this->membership->forceFill(['status' => $status])->save();
            $this->runReminders(0);
        }
        $this->membership->forceFill(['status' => 'active'])->save();
        $this->gym->forceFill(['status' => 'submitted'])->save();
        $this->runReminders(0);
    }
}
