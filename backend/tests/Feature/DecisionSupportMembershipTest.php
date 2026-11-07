<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportMembershipTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDecisionSupport();
    }

    public function test_opening_cohort_churn_and_raw_renewal_actions_are_event_based(): void
    {
        $opening = collect(range(1, 4))->map(fn () => $this->makeMembership(User::factory()->create()));
        foreach ($opening as $membership) $this->membershipEvent($membership, 'activated', '2026-10-01 10:00:00');
        $opening[0]->forceFill(['status' => 'frozen', 'ends_on' => null])->save();
        $sessionPlan = \App\Models\MembershipPlan::factory()->forGym($this->gym)->create(['billing_type' => 'sessions', 'duration_months' => null, 'session_count' => 10]);
        $opening[3]->forceFill(['membership_plan_id' => $sessionPlan->id, 'ends_on' => null])->save();
        $this->membershipEvent($opening[0], 'frozen', '2026-10-31 10:00:00');
        $this->membershipEvent($opening[1], 'expired', '2026-11-02 10:00:00');
        $this->membershipEvent($opening[1], 'cancelled', '2026-11-03 10:00:00');
        $this->membershipEvent($opening[1], 'activated', '2026-11-04 10:00:00');
        $this->membershipEvent($opening[2], 'cancelled', '2026-11-02 10:00:00');
        $new = $this->makeMembership(User::factory()->create());
        $this->membershipEvent($new, 'activated', '2026-11-01 00:00:00');
        $this->membershipEvent($new, 'expired', '2026-11-03 10:00:00');
        $never = $this->makeMembership(User::factory()->create(), ['status' => 'cancelled']);
        $this->membershipEvent($never, 'cancelled', '2026-11-04 10:00:00');
        $unpaid = $this->makeMembership(User::factory()->create(), ['status' => 'awaiting_payment']);
        $this->membershipEvent($unpaid, 'activated', '2026-09-01 10:00:00');
        $this->membershipEvent($unpaid, 'expired', '2026-10-01 10:00:00');
        $this->membershipEvent($unpaid, 'renewed', '2026-10-31 10:00:00');
        foreach ([$unpaid, $opening[0], $opening[1]] as $membership) $this->membershipEvent($membership, 'renewed', '2026-11-03 12:00:00');
        $this->membershipEvent($unpaid, 'unfrozen', '2026-10-31 12:00:00');

        $url = $this->decisionUrl('memberships').'?from=2026-11-01&to=2026-11-07';
        $response = $this->actingAs($this->owner)->getJson($url)->assertOk()
            ->assertJsonPath('summary.new', 1)->assertJsonPath('summary.renewed', 3)
            ->assertJsonPath('summary.expired', 2)->assertJsonPath('summary.cancelled', 3)
            ->assertJsonPath('summary.active_at_start', 4)->assertJsonPath('summary.churned_from_start', 2)
            ->assertJsonPath('summary.retained_from_start', 2)->assertJsonPath('summary.retention_rate', 50)
            ->assertJsonPath('summary.renewal_rate', 60)->assertJsonPath('summary.active_at_end', 3);
        foreach ($opening as $membership) $membership->forceFill(['status' => 'cancelled', 'starts_on' => '2027-01-01', 'ends_on' => '2027-02-01'])->save();
        $this->getJson($url)->assertOk()->assertJsonPath('summary.active_at_start', $response->json('summary.active_at_start'))
            ->assertJsonPath('summary.retention_rate', 50);
    }

    public function test_same_timestamp_id_order_first_activation_and_missing_history(): void
    {
        $inactive = $this->makeMembership(User::factory()->create());
        $this->membershipEvent($inactive, 'activated', '2026-10-31 23:59:59');
        $this->membershipEvent($inactive, 'cancelled', '2026-10-31 23:59:59');
        $active = $this->makeMembership(User::factory()->create());
        $this->membershipEvent($active, 'expired', '2026-10-31 23:59:59');
        $this->membershipEvent($active, 'activated', '2026-10-31 23:59:59');
        $this->membershipEvent($active, 'activated', '2026-11-01 10:00:00');
        $new = $this->makeMembership(User::factory()->create());
        $this->membershipEvent($new, 'activated', '2026-11-01 23:59:59.999999');
        $this->membershipEvent($new, 'activated', '2026-11-02 00:00:00');
        $this->makeMembership(User::factory()->create(), ['status' => 'frozen']);
        $this->actingAs($this->owner)->getJson($this->decisionUrl('memberships').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('summary.active_at_start', 1)->assertJsonPath('summary.new', 1)
            ->assertJsonPath('summary.active_at_end', 2)->assertJsonPath('summary.missing_activation_history', 1)
            ->assertJsonPath('summary.retention_rate', 100)->assertJsonPath('buckets.0.new', 1);
    }

    public function test_empty_null_rates_and_zero_filled_months(): void
    {
        $this->actingAs($this->owner)->getJson($this->decisionUrl('memberships').'?from=2026-10-01&to=2026-12-01')
            ->assertOk()->assertJsonPath('meta.has_data', false)->assertJsonPath('summary.new', 0)
            ->assertJsonPath('summary.retention_rate', null)->assertJsonPath('summary.renewal_rate', null)
            ->assertJsonCount(3, 'buckets')->assertJsonPath('buckets.1.period', '2026-11');
    }

    public function test_no_period_events_can_still_have_opening_stock(): void
    {
        $membership = $this->makeMembership($this->member, ['status' => 'frozen', 'ends_on' => null]);
        $this->membershipEvent($membership, 'activated', '2026-10-01 10:00:00');
        $this->membershipEvent($membership, 'frozen', '2026-10-02 10:00:00');
        $this->actingAs($this->owner)->getJson($this->decisionUrl('memberships').'?from=2026-11-01&to=2026-11-01')
            ->assertOk()->assertJsonPath('meta.has_data', true)->assertJsonPath('summary.new', 0)
            ->assertJsonPath('summary.active_at_start', 1)->assertJsonPath('summary.active_at_end', 1)
            ->assertJsonPath('summary.retention_rate', 100)->assertJsonPath('summary.renewal_rate', null);
    }
}
