<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\StockItem;
use App\Support\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\DecisionSupportSetup;
use Tests\TestCase;

class DecisionSupportTenancyTest extends TestCase
{
    use RefreshDatabase, DecisionSupportSetup;

    public function test_all_views_keep_exact_values_when_viewer_owns_two_distinct_gyms(): void
    {
        $this->setUpDecisionSupport();
        $first = [$this->gym, $this->owner, $this->member, $this->plan];
        $this->seedIndicators(1, '111.00');
        $before = $this->readViews();

        $this->setUpFinanceGym();
        $secondOwner = $this->owner;
        $this->gym->addPerson($first[1], GymPersonType::Owner);
        $this->owner = $first[1];
        $this->seedIndicators(3, '999.00');
        $foreign = $this->readViews();
        $this->assertIndicators($foreign, 3, '999.00');
        $foreignGym = $this->gym;

        $ownSession = DB::table('training_sessions')->where('gym_id', $first[0]->id)->value('id');
        $ownMembership = DB::table('memberships')->where('gym_id', $first[0]->id)->value('id');
        $ownRun = DB::table('payroll_runs')->where('gym_id', $first[0]->id)->value('id');
        $foreignBooking = DB::table('session_bookings')->where('gym_id', $foreignGym->id)->value('id');
        DB::table('session_bookings')->where('id', $foreignBooking)->update(['training_session_id' => $ownSession]);
        DB::table('membership_events')->where('gym_id', $foreignGym->id)->update(['membership_id' => $ownMembership]);
        DB::table('payments')->where('gym_id', $foreignGym->id)->update(['membership_id' => $ownMembership]);
        DB::table('payroll_lines')->where('gym_id', $foreignGym->id)->update(['payroll_run_id' => $ownRun]);

        [$this->gym, $this->owner, $this->member, $this->plan] = $first;
        app(CurrentGym::class)->set($this->gym);
        $after = $this->readViews();
        $this->assertSame($before, $after);
        $this->assertIndicators($after, 1, '111.00');
        $this->actingAs($secondOwner)->getJson($this->decisionUrl())->assertNotFound();
        $this->assertNotSame($this->gym->id, $foreignGym->id);
    }

    private function seedIndicators(int $count, string $amount): void
    {
        for ($index = 0; $index < $count; $index++) {
            $session = $this->occurrence('2026-11-02 10:00:00', ['name' => 'Tenant '.$this->gym->id]);
            $this->markedBooking($session, 'present', user: $this->member);
            $this->progress($this->member);
            $membership = $this->makeMembership(\App\Models\User::factory()->create());
            $this->membershipEvent($membership, 'activated', '2026-11-02 10:00:00');
            $this->customerAt('2026-11-02 10:00:00');
            StockItem::create(['name' => 'Tenant '.$this->gym->id.' item '.$index, 'category' => 'equipment', 'unit' => 'piece', 'location' => 'Store']);
        }
        $this->income('other', $amount);
        $this->income('other', $amount, '2026-10-02 10:00:00');
        $this->payrollFixture('2026-11-05', lines: [['gross_pay' => $amount, 'net_pay' => $amount]]);
    }

    private function readViews(): array
    {
        $result = [];
        foreach (['attendance', 'memberships', 'athletes', 'services', 'customers', 'employees', 'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'] as $view) {
            $result[$view] = $this->actingAs($this->owner)->getJson($this->decisionUrl($view).'?from=2026-11-01&to=2026-11-07')->assertOk()->json();
        }

        return $result;
    }

    private function assertIndicators(array $data, int $count, string $amount): void
    {
        $this->assertSame($count, $data['attendance']['summary']['attended']);
        $this->assertSame($count, $data['memberships']['summary']['new']);
        $this->assertSame($count, $data['athletes']['summary']['promotion']);
        $this->assertSame($count, $data['services']['summary']['sessions']);
        $this->assertSame($count, $data['customers']['summary']['leads']);
        $this->assertSame($amount, $data['employees']['payroll']['totals']['gross']);
        $this->assertSame($amount, $data['money']['income_expense']['totals']['income']);
        $this->assertSame($count, $data['stock']['summary']['items']);
        $this->assertSame($count, $data['trends']['totals']['attended']);
        $this->assertSame($amount, $data['forecast']['history'][0]['income']);
        $this->assertSame($count >= 3 ? 1 : 0, $data['problems']['summary']['low_use_slot']);
        $this->assertSame($amount, $data['sustainability']['financial']['totals']['income']);
    }
}
