<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\PlanBilling;
use App\Notifications\MembershipUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-36: renew, freeze, unfreeze, cancel and expire, each kept in the history. */
class MembershipLifecycleTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    public function test_freeze_moves_the_end_date_and_respects_the_plan_limit(): void
    {
        $membership = $this->active();
        $endsOn = $membership->ends_on->copy();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/freeze"), ['days' => 31])
            ->assertJsonValidationErrors(['days']);

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/freeze"), ['days' => 10])
            ->assertOk()
            ->assertJsonPath('membership.status', 'frozen')
            ->assertJsonPath('membership.ends_on', $endsOn->copy()->addDays(10)->toDateString());

        // Unfreezing on the same day gives all ten days back.
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/unfreeze"))
            ->assertOk()
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.ends_on', $endsOn->toDateString())
            ->assertJsonPath('membership.freeze_days_used', 0);
    }

    public function test_plans_without_freezing_cannot_be_frozen(): void
    {
        $this->plan->forceFill(['max_freeze_days' => null])->save();
        $membership = $this->active();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/freeze"), ['days' => 5])
            ->assertStatus(422);
    }

    public function test_renewing_extends_an_active_membership_and_restarts_an_expired_one(): void
    {
        $membership = $this->active();
        $endsOn = $membership->ends_on->copy();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/renew"))
            ->assertOk()
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.ends_on', $endsOn->copy()->addMonthNoOverflow()->toDateString())
            ->assertJsonPath('membership.balance', '1500.00');

        $membership->refresh();
        $membership->forceFill(['status' => MembershipStatus::Expired])->save();
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/renew"))
            ->assertJsonPath('membership.status', 'awaiting_payment')
            ->assertJsonPath('membership.balance', '3000.00');
    }

    public function test_cancel_needs_a_reason_and_refunds_follow_the_plan(): void
    {
        $membership = $this->active();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/cancel"), [])
            ->assertJsonValidationErrors(['reason']);
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/cancel"), [
            'reason' => 'Moving away', 'refund_amount' => 2000, 'refund_method' => 'cash',
        ])->assertJsonValidationErrors(['refund_amount']);

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/cancel"), [
            'reason' => 'Moving away', 'refund_amount' => 500, 'refund_method' => 'cash',
        ])->assertOk()
            ->assertJsonPath('membership.status', 'cancelled')
            ->assertJsonPath('membership.amount_paid', '1000.00');

        $this->plan->forceFill(['refundable' => false])->save();
        $second = $this->active();
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$second->id}/cancel"), [
            'reason' => 'Changed mind', 'refund_amount' => 100, 'refund_method' => 'cash',
        ])->assertJsonValidationErrors(['refund_amount']);
    }

    public function test_only_members_managers_change_a_membership(): void
    {
        $membership = $this->active();
        $viewer = $this->employeeWith(['members' => 'view']);

        foreach (['renew', 'freeze', 'unfreeze', 'cancel'] as $action) {
            $this->actingAs($viewer)->postJson($this->url("memberships/{$membership->id}/{$action}"), ['days' => 1, 'reason' => 'x'])
                ->assertForbidden();
        }
    }

    public function test_the_daily_job_expires_memberships_and_ends_freezes(): void
    {
        Notification::fake();
        $expiring = $this->active();
        $expiring->forceFill(['ends_on' => today()->subDay()])->save();

        $this->artisan('app:expire-memberships')->assertSuccessful();
        $this->assertSame(MembershipStatus::Expired, $this->membership($expiring->id)->status);
        Notification::assertSentTo($this->applicant, MembershipUpdated::class, fn ($n) => $n->toArray($this->applicant)['title'] === 'Membership expired');

        $this->artisan('app:expire-memberships');
        $this->assertSame(MembershipStatus::Expired, $this->membership($expiring->id)->status);
    }

    public function test_frozen_memberships_unfreeze_when_the_freeze_ends_and_session_passes_expire_when_used_up(): void
    {
        $membership = $this->active();
        $membership->forceFill(['status' => MembershipStatus::Frozen, 'frozen_until' => today()->subDay()])->save();

        $this->artisan('app:expire-memberships');
        $this->assertSame(MembershipStatus::Active, $this->membership($membership->id)->status);

        $this->plan->forceFill(['billing_type' => PlanBilling::Sessions, 'duration_months' => null, 'session_count' => 10])->save();
        $membership->forceFill(['sessions_remaining' => 0, 'ends_on' => null])->save();
        $this->artisan('app:expire-memberships');
        $this->assertSame(MembershipStatus::Expired, $this->membership($membership->id)->status);
    }

    public function test_every_change_is_kept_in_the_history_the_athlete_sees(): void
    {
        $membership = $this->active();
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/freeze"), ['days' => 3]);

        $types = collect($this->actingAs($this->applicant)->getJson("/api/my-memberships/{$membership->id}")
            ->assertOk()->json('membership.history'))->pluck('type')->all();

        $this->assertSame(['frozen', 'activated', 'payment', 'approved', 'applied'], $types);
    }
}
