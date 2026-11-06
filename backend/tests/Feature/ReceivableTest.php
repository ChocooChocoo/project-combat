<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\SessionBooking;
use App\Models\User;
use App\Support\BookingLedger;
use App\Support\CurrentGym;
use App\Support\MembershipLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\FinanceSetup;
use Tests\TestCase;

/** R-65: outstanding balances match the ledgers and are private to each member. */
class ReceivableTest extends TestCase
{
    use FinanceSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceGym();
    }

    public function test_every_receivable_row_matches_the_membership_and_booking_ledgers(): void
    {
        $miaMembership = $this->makeMembership($this->member, ['amount_charged' => 1000]);
        $this->payMembership($miaMembership, '300.00');

        $requestedSession = $this->makeSession('classes', '150.00', 'Beginner boxing');
        $requested = $this->makeBooking($this->member, $requestedSession, 'requested', '150.00', $miaMembership);
        $this->payBooking($requested, '50.00');

        $privateSession = $this->makeSession('private_coaching', '500.00', 'Private pad work');
        $private = $this->makeBooking($this->member, $privateSession, 'requested', '500.00', $miaMembership);

        $paidMembership = $this->makeMembership($this->member, ['amount_charged' => 500]);
        $this->payMembership($paidMembership, '500.00');
        $paidSession = $this->makeSession('classes', '150.00', 'Paid class');
        $paidBooking = $this->makeBooking($this->member, $paidSession, 'booked', '150.00', $paidMembership);
        $this->payBooking($paidBooking, '150.00');
        $cancelledSession = $this->makeSession('classes', '150.00', 'Cancelled class');
        $cancelled = $this->makeBooking($this->member, $cancelledSession, 'cancelled', '150.00', $miaMembership);

        $noah = User::factory()->create(['first_name' => 'Noah', 'last_name' => 'Reyes']);
        $this->gym->addPerson($noah, GymPersonType::Member);
        $noahMembership = $this->makeMembership($noah, ['amount_charged' => 500]);
        $bookedSession = $this->makeSession('classes', '200.00', 'Advanced boxing');
        $booked = $this->makeBooking($noah, $bookedSession, 'booked', '200.00', $noahMembership);

        $response = $this->actingAs($this->owner)->getJson($this->url('finance/receivables'))->assertOk();
        $receivables = collect($response->json('receivables'));

        $this->assertCount(2, $receivables);
        $mia = $receivables->firstWhere('user_id', $this->member->id);
        $noahRow = $receivables->firstWhere('user_id', $noah->id);
        $this->assertSame('Mia Torres', $mia['member_name']);
        $this->assertSame('1300.00', $mia['total']);
        $this->assertCount(3, $mia['sources']);
        $this->assertSame('700.00', $noahRow['total']);
        $this->assertCount(2, $noahRow['sources']);

        $kinds = collect($mia['sources'])->pluck('kind')->all();
        $this->assertContains('membership', $kinds);
        $this->assertContains('booking', $kinds);
        $this->assertContains('private_coaching', $kinds);
        $membershipSources = collect($mia['sources'])->where('kind', 'membership')->pluck('id');
        $bookingSources = collect($mia['sources'])->whereIn('kind', ['booking', 'private_coaching'])->pluck('id');
        $this->assertContains($requested->id, $bookingSources->all());
        $this->assertContains($private->id, $bookingSources->all());
        $this->assertNotContains($paidMembership->id, $membershipSources->all());
        $this->assertNotContains($paidBooking->id, $bookingSources->all());
        $this->assertNotContains($cancelled->id, $bookingSources->all());

        $membershipLedger = app(MembershipLedger::class);
        $bookingLedger = app(BookingLedger::class);
        foreach ($receivables as $row) {
            foreach ($row['sources'] as $source) {
                if ($source['kind'] === 'membership') {
                    $model = Membership::withoutGlobalScope('gym')->findOrFail($source['id']);
                    $this->assertSame(number_format($membershipLedger->balance($model), 2, '.', ''), $source['amount']);
                } else {
                    $model = SessionBooking::withoutGlobalScope('gym')->findOrFail($source['id']);
                    $this->assertSame(number_format($bookingLedger->balance($model), 2, '.', ''), $source['amount']);
                }
            }
        }
    }

    public function test_a_payment_reduces_the_balance_and_a_member_sees_only_their_own(): void
    {
        $membership = $this->makeMembership($this->member, ['amount_charged' => 1000]);

        $this->actingAs($this->member)->getJson('/api/my-balance')
            ->assertOk()->assertJsonPath('balance', '1000.00')->assertJsonCount(1, 'sources');

        $this->payMembership($membership, '400.00');

        $mine = $this->actingAs($this->member)->getJson('/api/my-balance')
            ->assertOk()->assertJsonPath('balance', '600.00')->assertJsonCount(1, 'sources');
        $this->assertSame('600.00', $mine->json('sources.0.amount'));
        $this->assertSame('membership', $mine->json('sources.0.kind'));

        $other = User::factory()->create(['first_name' => 'Noah', 'last_name' => 'Reyes']);
        $this->gym->addPerson($other, GymPersonType::Member);
        $this->makeMembership($other, ['amount_charged' => 900]);

        $mineAgain = $this->actingAs($this->member)->getJson('/api/my-balance')->assertOk();
        $this->assertSame('600.00', $mineAgain->json('balance'));
        $this->assertSame([$membership->id], collect($mineAgain->json('sources'))->pluck('id')->all());

        $this->actingAs($this->owner)->getJson($this->url('finance/receivables'))->assertOk()
            ->assertJsonCount(2, 'receivables');
    }

    public function test_receivables_need_their_own_permission(): void
    {
        $this->makeMembership($this->member, ['amount_charged' => 1000]);

        $viewer = $this->employeeWithFinanceRole(['finance.receivables.view']);
        $paymentsOnly = $this->employeeWithFinanceRole(['finance.payments.view']);
        $plain = $this->employeeWithFinanceRole([]);

        $this->actingAs($viewer)->getJson($this->url('finance/receivables'))
            ->assertOk()->assertJsonCount(1, 'receivables');
        $this->actingAs($paymentsOnly)->getJson($this->url('finance/receivables'))->assertForbidden();
        $this->actingAs($plain)->getJson($this->url('finance/receivables'))->assertForbidden();
    }

    public function test_my_balance_combines_gyms_and_can_be_zero(): void
    {
        app(CurrentGym::class)->set($this->gym);
        $this->makeMembership($this->member, ['amount_charged' => 400]);

        $secondGym = Gym::factory()->approved()->create();
        app(CurrentGym::class)->set($secondGym);
        $secondPlan = MembershipPlan::factory()->forGym($secondGym)->create([
            'published_at' => now(), 'total_fee' => 600, 'billing_type' => 'time', 'duration_months' => 1,
        ]);
        $second = new Membership;
        $second->fill([
            'user_id' => $this->member->id,
            'membership_plan_id' => $secondPlan->id,
            'emergency_contacts' => [['name' => 'Contact', 'relationship' => 'Parent', 'phone' => '0917 000 0000']],
            'health_answers' => array_fill_keys(array_keys(config('membership.health_questions')), false),
            'waivers' => array_keys(config('membership.waivers')),
            'signature_name' => 'Mia Torres',
            'signed_on' => today()->toDateString(),
        ]);
        $second->forceFill(['status' => 'active', 'amount_charged' => 600])->save();

        $this->actingAs($this->member)->getJson('/api/my-balance')
            ->assertOk()->assertJsonPath('balance', '1000.00')->assertJsonCount(2, 'sources');

        $empty = User::factory()->create();
        $this->gym->addPerson($empty, GymPersonType::Member);

        $this->actingAs($empty)->getJson('/api/my-balance')
            ->assertOk()->assertJsonPath('balance', '0.00')->assertJsonCount(0, 'sources');
    }
}
