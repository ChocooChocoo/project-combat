<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** A walk-in sale has no account, and the payments list must still load. */
class WalkInPaymentListTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    public function test_payments_list_loads_with_a_walk_in_payment(): void
    {
        $this->setUpMembershipGym();
        $membership = $this->approved();
        $desk = $this->employeeWith(['finance' => 'manage']);
        $this->actingAs($desk)->postJson($this->url('payments'), ['membership_id' => $membership->id, 'amount' => 100, 'method' => 'cash'])->assertCreated();
        DB::table('payments')->update(['user_id' => null, 'payer_name' => 'Walk-in Wally']);

        $this->actingAs($desk)->getJson($this->url('payments'))->assertOk()
            ->assertJsonPath('payments.0.member.name', 'Walk-in Wally');
    }
}
