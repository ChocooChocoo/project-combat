<?php

namespace Tests\Feature;

use App\Models\OvertimeRequest;
use App\Notifications\OvertimeRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\AttendanceSetup;
use Tests\TestCase;

/** R-57: extra time becomes a request; only a different permitted person decides it. */
class OvertimeTest extends TestCase
{
    use AttendanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttendanceGym();
        $this->scheduleFor($this->employee);
    }

    private function automaticRequest(): OvertimeRequest
    {
        $this->travelTo(Carbon::parse('2026-11-05 09:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-in'))->assertCreated();
        $this->travelTo(Carbon::parse('2026-11-05 18:00:00'));
        $this->actingAs($this->employee)->postJson($this->url('my-attendance/clock-out'))->assertOk();

        return OvertimeRequest::firstOrFail();
    }

    public function test_approved_minutes_are_capped_at_the_request(): void
    {
        $request = $this->automaticRequest();

        $this->actingAs($this->owner)->postJson($this->url("overtime-requests/{$request->id}/decision"), [
            'decision' => 'approve',
            'minutes' => 999,
        ])->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.approved_minutes', 60);
    }

    public function test_a_person_cannot_decide_their_own_request(): void
    {
        $this->giveRole($this->employee, ['hr.overtime.approve'], 'Overtime approver');

        $request = $this->automaticRequest();

        $this->actingAs($this->employee)->postJson($this->url("overtime-requests/{$request->id}/decision"), [
            'decision' => 'approve',
        ])->assertForbidden();
    }

    public function test_a_rejection_keeps_no_approved_minutes(): void
    {
        $request = $this->automaticRequest();

        $this->actingAs($this->owner)->postJson($this->url("overtime-requests/{$request->id}/decision"), [
            'decision' => 'reject',
            'note' => 'Not agreed in advance.',
        ])->assertOk()
            ->assertJsonPath('request.status', 'rejected')
            ->assertJsonPath('request.approved_minutes', null);
    }

    public function test_a_correction_sends_an_approved_request_back_to_pending(): void
    {
        $request = $this->automaticRequest();

        $this->actingAs($this->owner)->postJson($this->url("overtime-requests/{$request->id}/decision"), [
            'decision' => 'approve',
        ])->assertOk();

        $attendance = $request->attendance;
        $this->actingAs($this->owner)->putJson($this->url("staff-attendance/{$attendance->id}"), [
            'clock_out_time' => '17:10',
            'reason' => 'The employee left on time.',
        ])->assertOk();

        $request->refresh();
        $this->assertSame('pending', $request->status->value);
        $this->assertNull($request->approved_minutes);
        $this->assertSame(0, $request->requested_minutes);
    }

    public function test_a_manual_request_notifies_an_approver(): void
    {
        Notification::fake();

        $this->actingAs($this->employee)->postJson($this->url('my-overtime-requests'), [
            'work_date' => '2026-11-05',
            'minutes' => 45,
            'reason' => 'Stayed to close the gym.',
        ])->assertCreated()
            ->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.kind', 'manual');

        Notification::assertSentTo($this->owner, OvertimeRequested::class);
    }
}
