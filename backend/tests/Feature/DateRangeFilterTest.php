<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-112: from/to filters on history lists hide rows outside the range; no range keeps the old list. */
class DateRangeFilterTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    public function test_payments_filter_by_paid_date(): void
    {
        $membership = $this->approved();
        $this->travelTo('2026-10-05 10:00:00');
        $this->actingAs($this->owner)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 500, 'method' => 'cash'])->assertCreated();
        $this->travelTo('2026-11-05 23:30:00');
        $this->actingAs($this->owner)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 500, 'method' => 'cash'])->assertCreated();
        $this->travelBack();

        $this->assertSame(2, Payment::withoutGlobalScope('gym')->count());
        $this->actingAs($this->owner)->getJson($this->url('payments'))->assertJsonCount(2, 'payments');
        $this->actingAs($this->owner)->getJson($this->url('payments?from=2026-11-05&to=2026-11-05'))->assertJsonCount(1, 'payments');
        $this->actingAs($this->owner)->getJson($this->url('payments?to=2026-10-31'))->assertJsonCount(1, 'payments');
        $this->actingAs($this->owner)->getJson($this->url('payments?from=2026-11-06&to=2026-11-01'))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_leave_and_payroll_use_period_overlap(): void
    {
        LeaveRequest::factory()->forGym($this->gym)->create(['starts_on' => '2026-10-28', 'ends_on' => '2026-11-02']);
        LeaveRequest::factory()->forGym($this->gym)->create(['starts_on' => '2026-11-20', 'ends_on' => '2026-11-21']);

        $this->actingAs($this->owner)->getJson($this->url('leave-requests'))->assertJsonCount(2, 'requests');
        $this->actingAs($this->owner)->getJson($this->url('leave-requests?from=2026-11-01&to=2026-11-10'))->assertJsonCount(1, 'requests');
        $this->actingAs($this->owner)->getJson($this->url('payroll/runs?from=2026-11-10&to=2026-11-01'))->assertUnprocessable();
    }

    public function test_notifications_filter_by_created_date(): void
    {
        foreach (['2026-10-01 08:00:00', '2026-11-03 08:00:00'] as $at) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => $this->owner->getMorphClass(),
                'notifiable_id' => $this->owner->id, 'data' => '{}', 'created_at' => $at, 'updated_at' => $at,
            ]);
        }

        $this->actingAs($this->owner)->getJson('/api/notifications')->assertJsonCount(2, 'notifications.data');
        $this->actingAs($this->owner)->getJson('/api/notifications?from=2026-11-01')->assertJsonCount(1, 'notifications.data');
        $this->actingAs($this->owner)->getJson('/api/notifications?to=bad')->assertUnprocessable();
    }

    public function test_other_lists_accept_the_range(): void
    {
        foreach (['bookings', 'overtime-requests', 'purchase-requests', 'purchase-orders', 'follow-ups', 'announcements', 'my-attendance'] as $path) {
            $this->actingAs($this->owner)->getJson($this->url("{$path}?from=2026-11-10&to=2026-11-01"))->assertUnprocessable();
            $this->actingAs($this->owner)->getJson($this->url("{$path}?from=2026-11-01&to=2026-11-10"))->assertSuccessful();
        }
        $this->actingAs($this->applicant)->getJson('/api/my-bookings?from=2026-11-01&to=2026-11-10')->assertOk();
    }
}
