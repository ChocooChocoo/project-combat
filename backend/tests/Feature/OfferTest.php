<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-111: promos and vouchers only work after approval, inside their dates, plans and use limit. */
class OfferTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    private function offer(array $overrides = []): array
    {
        return [
            'kind' => 'voucher', 'name' => 'Welcome', 'code' => 'hello10', 'discount_type' => 'percent', 'value' => 10,
            'starts_on' => today()->toDateString(), 'ends_on' => null, 'max_uses' => null, 'plan_ids' => [$this->plan->id],
            ...$overrides,
        ];
    }

    /** A fresh applicant each time, since one person can hold only one live application. */
    private function approveWith(?string $voucher = null)
    {
        $this->applicant = User::factory()->create();
        $id = $this->actingAs($this->applicant)
            ->postJson($this->url('membership-applications'), $this->applicationPayload($voucher ? ['voucher_code' => $voucher] : []))
            ->assertCreated()->json('membership.id');

        return $this->actingAs($this->owner)->postJson($this->url("memberships/{$id}/decision"), ['decision' => 'approve']);
    }

    public function test_staff_voucher_waits_for_approval_and_works_after(): void
    {
        $crm = $this->employeeWith(['customers' => 'manage']);
        $id = $this->actingAs($crm)->postJson($this->url('offers'), $this->offer())
            ->assertCreated()->assertJsonPath('offer.status', 'pending')->assertJsonPath('offer.code', 'HELLO10')->json('offer.id');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->owner->id]);
        $this->approveWith('HELLO10')->assertJsonValidationErrors('voucher_code');

        $this->actingAs($crm)->postJson($this->url("offers/{$id}/decision"), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url("offers/{$id}/decision"), ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('offer.status', 'approved');

        $this->approveWith('hello10')->assertOk()
            ->assertJsonPath('membership.amount_charged', '1350.00')
            ->assertJsonPath('membership.discount_amount', '150.00')
            ->assertJsonPath('membership.offer_name', 'Welcome');
        $this->assertSame(1, Offer::withoutGlobalScope('gym')->find($id)->uses);
    }

    public function test_owner_promo_is_live_at_once_and_applies_by_itself(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['kind' => 'promo', 'code' => null, 'discount_type' => 'fixed', 'value' => 200]))
            ->assertCreated()->assertJsonPath('offer.status', 'approved');

        $this->approveWith()->assertOk()->assertJsonPath('membership.amount_charged', '1300.00');
    }

    public function test_price_preview_shows_the_discount_the_approval_will_charge(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer())->assertCreated();
        $this->applicant = User::factory()->create();
        $id = $this->actingAs($this->applicant)->postJson($this->url('membership-applications'), $this->applicationPayload())
            ->assertCreated()->json('membership.id');
        $preview = fn (string $code = '') => $this->actingAs($this->owner)->getJson($this->url("memberships/{$id}/price-preview?voucher_code={$code}"));

        $preview()->assertOk()->assertJsonPath('price.offer_discount', 0)->assertJsonPath('price.payable', 1500);
        $preview('hello10')->assertOk()->assertJsonPath('price.offer_name', 'Welcome')
            ->assertJsonPath('price.offer_discount', 150)->assertJsonPath('price.payable', 1350);
        $preview('NOPE')->assertJsonValidationErrors('voucher_code');
        $this->actingAs($this->employeeWith(['customers' => 'manage']))->getJson($this->url("memberships/{$id}/price-preview"))->assertForbidden();
        $this->assertSame(0, Offer::withoutGlobalScope('gym')->first()->uses);

        $this->actingAs($this->owner)->postJson($this->url("memberships/{$id}/decision"), ['decision' => 'approve', 'voucher_code' => 'HELLO10'])
            ->assertOk()->assertJsonPath('membership.amount_charged', '1350.00');
        $preview()->assertStatus(409);
    }

    public function test_staff_edit_of_approved_offer_needs_approval_again(): void
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer())->json('offer.id');
        $crm = $this->employeeWith(['customers' => 'manage']);
        $this->actingAs($crm)->putJson($this->url("offers/{$id}"), $this->offer(['value' => 50]))
            ->assertOk()->assertJsonPath('offer.status', 'pending');
    }

    public function test_expired_used_up_and_wrong_plan_vouchers_are_refused(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['code' => 'OLD', 'starts_on' => today()->subDays(10)->toDateString(), 'ends_on' => today()->subDay()->toDateString()]))->assertCreated();
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['code' => 'ONCE', 'max_uses' => 1]))->assertCreated();
        $other = $this->plan->replicate();
        $other->save();
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['code' => 'OTHER', 'plan_ids' => [$other->id]]))->assertCreated();

        $this->approveWith('OLD')->assertJsonValidationErrors('voucher_code');
        $this->approveWith('OTHER')->assertJsonValidationErrors('voucher_code');

        $this->approveWith('ONCE')->assertOk();
        $this->approveWith('ONCE')->assertJsonValidationErrors('voucher_code');
    }

    public function test_offers_need_permission_and_a_percent_cannot_pass_100(): void
    {
        $coach = $this->employeeWith(['training' => 'manage']);
        $this->actingAs($coach)->getJson($this->url('offers'))->assertForbidden();
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['value' => 120]))->assertJsonValidationErrors('value');
        $this->actingAs($this->owner)->postJson($this->url('offers'), $this->offer(['code' => null]))->assertJsonValidationErrors('code');
    }
}
