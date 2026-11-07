<?php

namespace Tests\Feature;

use App\Models\CustomerFollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Tests\Feature\Concerns\CustomerSetup;
use Tests\TestCase;

class FollowUpReminderTest extends TestCase
{
    use CustomerSetup, RefreshDatabase;

    private CustomerFollowUp $followUp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-09 09:00:00'));
        $this->setUpCustomerGym();
        $customer = $this->addCustomer();
        $this->followUp = $customer->followUps()->create([
            'assigned_to' => $this->employee->id, 'due_at' => now(), 'note' => 'Confirm beginner boxing.',
        ]);
    }

    private function runReminders(int $expected): void
    {
        $this->app->forgetScopedInstances();
        $this->artisan('app:send-follow-up-reminders')->expectsOutput("{$expected} follow-up reminders sent.")->assertSuccessful();
    }

    public function test_the_assignee_is_reminded_when_a_follow_up_is_due_once(): void
    {
        $this->runReminders(1);
        $this->runReminders(0);
        $notice = $this->employee->notifications()->sole()->data;
        $this->assertSame($this->followUp->id, $notice['follow_up_id']);
        $this->assertSame("/gyms/{$this->gym->id}/manage/customers/{$this->followUp->customer_id}", $notice['url']);
        $this->assertStringContainsString('Rico Dizon', $notice['message']);
        $this->assertStringContainsString('Confirm beginner boxing.', $notice['message']);
        $this->assertNotNull($this->followUp->fresh()->reminded_at);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_follow_ups_older_than_the_lookback_window_are_skipped(): void
    {
        $this->followUp->update(['due_at' => now()->subHours(24)->subSecond()]);
        $this->runReminders(0);
        $this->followUp->update(['due_at' => now()->subHours(24)]);
        $this->runReminders(1);
    }

    public function test_reassignment_clears_the_marker_for_the_new_assignee(): void
    {
        $this->runReminders(1);
        $this->actingAs($this->owner)->putJson($this->url("follow-ups/{$this->followUp->id}"), ['assigned_to' => $this->owner->id])->assertOk();
        $this->assertNull($this->followUp->fresh()->reminded_at);
        $this->runReminders(1);
        $this->runReminders(0);
        $this->assertSame(1, $this->owner->notifications()->where('type', \App\Notifications\FollowUpDueReminder::class)->count());
    }

    public function test_a_new_due_date_clears_the_marker(): void
    {
        $this->runReminders(1);
        $this->actingAs($this->owner)->putJson($this->url("follow-ups/{$this->followUp->id}"), ['note' => 'Only note changed.'])->assertOk();
        $this->assertNotNull($this->followUp->fresh()->reminded_at);
        $this->putJson($this->url("follow-ups/{$this->followUp->id}"), ['due_at' => '2026-11-09 10:00:00'])->assertOk();
        $this->assertNull($this->followUp->fresh()->reminded_at);
        $this->runReminders(0);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00'));
        $this->runReminders(1);
    }

    public function test_done_and_cancelled_follow_ups_are_skipped(): void
    {
        $this->followUp->update(['status' => 'done']);
        $this->followUp->replicate()->fill(['status' => 'cancelled'])->save();
        $this->runReminders(0);
    }

    public function test_the_three_reminder_commands_are_registered(): void
    {
        foreach ([
            'app:send-session-reminders' => '*/5 * * * *',
            'app:send-membership-reminders' => '0 8 * * *',
            'app:send-follow-up-reminders' => '*/15 * * * *',
        ] as $command => $expression) {
            $events = collect(Schedule::events())->filter(fn ($event) => str_contains($event->command ?? '', $command));
            $this->assertCount(1, $events);
            $this->assertSame($expression, $events->first()->expression);
            $this->assertTrue($events->first()->withoutOverlapping);
        }
    }

    public function test_unapproved_gyms_are_skipped(): void
    {
        $this->gym->forceFill(['status' => 'submitted'])->save();
        $this->runReminders(0);
    }
}
