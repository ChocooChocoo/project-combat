<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\PlanBilling;
use App\Models\MembershipPlan;
use App\Support\MembershipPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-109 prepaying several terms and R-110 the price lock on renewal. */
class MembershipPricingTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym(['max_prepay_terms' => 6, 'prepay_discount_percent' => 10, 'duration_months' => 1, 'billing_type' => PlanBilling::Time]);
    }

    public function test_discount_math_applies_only_to_more_than_one_term(): void
    {
        $plan = new MembershipPlan(['prepay_discount_percent' => 12.5]);
        $this->assertSame(1500.0, MembershipPricing::charge($plan, 1500, 1));
        $this->assertSame(3937.5, MembershipPricing::charge($plan, 1500, 3));
        $this->assertSame(0.0, MembershipPricing::charge($plan, 0, 4));
    }

    public function test_prepaying_three_months_charges_once_and_sets_the_end_date(): void
    {
        $id = $this->actingAs($this->applicant)
            ->postJson($this->url('membership-applications'), $this->applicationPayload(['prepaid_terms' => 3]))
            ->assertCreated()->json('membership.id');
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$id}/decision"), ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('membership.amount_charged', '4050.00')
            ->assertJsonPath('membership.locked_fee', '1500.00')
            ->assertJsonPath('membership.prepaid_terms', 3);

        $this->actingAs($this->owner)->postJson($this->url('payments'), ['membership_id' => $id, 'amount' => 4050, 'method' => 'cash'])->assertCreated();
        $membership = $this->membership($id);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame(today()->addMonthsNoOverflow(3)->subDay()->toDateString(), $membership->ends_on->toDateString());
    }

    public function test_more_terms_than_the_plan_allows_are_refused(): void
    {
        $this->actingAs($this->applicant)
            ->postJson($this->url('membership-applications'), $this->applicationPayload(['prepaid_terms' => 7]))
            ->assertJsonValidationErrors('prepaid_terms');
    }

    public function test_sessions_plan_refuses_prepay(): void
    {
        $this->actingAs($this->owner)->putJson($this->url("plans/{$this->plan->id}"), [
            'name' => 'Ten pass', 'billing_type' => 'sessions', 'session_count' => 10, 'total_fee' => 2000,
            'max_prepay_terms' => 3, 'inclusions' => ['Classes'], 'policy_validity' => 'x', 'policy_freezing' => 'x',
            'policy_renewal' => 'x', 'policy_cancellation' => 'x',
        ])->assertJsonValidationErrors('max_prepay_terms');
    }

    public function test_price_change_never_touches_memberships_and_renewal_keeps_the_old_price(): void
    {
        $membership = $this->active();
        $endsOn = $membership->ends_on->copy();
        $this->plan->forceFill(['total_fee' => 2000])->save();

        $this->assertSame('1500.00', $this->membership($membership->id)->amount_charged);
        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/renew"), ['prepaid_terms' => 2])
            ->assertOk()
            ->assertJsonPath('membership.balance', '2700.00')
            ->assertJsonPath('membership.ends_on', $endsOn->copy()->addMonthsNoOverflow(2)->toDateString());
    }

    public function test_renewal_after_expiry_charges_and_locks_the_new_price(): void
    {
        $membership = $this->active();
        $this->plan->forceFill(['total_fee' => 2000])->save();
        $membership->forceFill(['status' => MembershipStatus::Expired])->save();

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$membership->id}/renew"))
            ->assertJsonPath('membership.status', 'awaiting_payment')
            ->assertJsonPath('membership.balance', '2000.00')
            ->assertJsonPath('membership.locked_fee', '2000.00');
    }
}
