<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\TrainingSession;
use App\Support\CurrentGym;
use App\Support\SessionScheduler;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public static function seedDates(): array
    {
        return [['2026-11-05'], ['2026-11-10'], ['2027-04-12']];
    }

    #[DataProvider('seedDates')]
    public function test_demo_data_covers_membership_and_training_workflows_consistently(string $date): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->travelTo(Carbon::parse($date)->setTime(10, 0));
        $this->seed();

        foreach (Gym::where('status', 'approved')->get() as $gym) {
            app(CurrentGym::class)->set($gym);
            $this->assertGreaterThan(0, DB::table('stock_items')->where('gym_id', $gym->id)->count());
            $this->assertGreaterThan(0, DB::table('stock_movements')->where('gym_id', $gym->id)->count());
            $this->assertSame(2, DB::table('suppliers')->where('gym_id', $gym->id)->count());
            $this->assertGreaterThan(0, DB::table('purchase_requests')->where('gym_id', $gym->id)->where('status', 'approved')->count());
            $this->assertGreaterThan(0, DB::table('purchase_orders')->where('gym_id', $gym->id)->where('status', 'partly_received')->count());
            $this->assertGreaterThan(0, DB::table('goods_receipts')->where('gym_id', $gym->id)->count());
            $this->assertGreaterThan(0, DB::table('supplier_payments')->where('gym_id', $gym->id)->count());
            foreach (['customers', 'customer_interactions', 'customer_follow_ups', 'customer_groups'] as $table) {
                $this->assertGreaterThan(0, DB::table($table)->where('gym_id', $gym->id)->count(), $table);
            }
            $groupIds = DB::table('customer_groups')->where('gym_id', $gym->id)->pluck('id');
            $this->assertGreaterThan(0, DB::table('customer_group_members')->whereIn('customer_group_id', $groupIds)->count());
            $this->assertDatabaseHas('customer_interactions', ['gym_id' => $gym->id, 'kind' => 'concern']);
            $this->assertDatabaseHas('customer_follow_ups', ['gym_id' => $gym->id, 'status' => 'open']);
            $this->assertDatabaseHas('customer_follow_ups', ['gym_id' => $gym->id, 'status' => 'done']);
            $this->assertDatabaseHas('customers', ['gym_id' => $gym->id, 'status' => 'lost']);
            $this->assertSame(
                ['applied', 'lead', 'lost', 'member'],
                $gym->customers()->get()->pluck('stage')->unique()->sort()->values()->all(),
            );
            $this->assertGreaterThanOrEqual(12, DB::table('memberships')->where('gym_id', $gym->id)->count());
            foreach (['active', 'applied', 'rejected', 'awaiting_payment', 'frozen', 'expired', 'cancelled'] as $status) {
                $this->assertDatabaseHas('memberships', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['booked', 'requested', 'waitlisted', 'rejected', 'cancelled'] as $status) {
                $this->assertDatabaseHas('session_bookings', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['present', 'late', 'absent'] as $attendance) {
                $this->assertDatabaseHas('session_bookings', ['gym_id' => $gym->id, 'attendance' => $attendance]);
            }
            foreach (['athlete_profiles', 'athlete_entries', 'payments', 'membership_events', 'gym_visits', 'session_notes'] as $table) {
                $this->assertGreaterThan(0, DB::table($table)->where('gym_id', $gym->id)->count(), $table);
            }
            $this->assertSame(1, DB::table('payroll_runs')->where('gym_id', $gym->id)->where('status', 'draft')->count());
            $released = DB::table('payroll_runs')->where('gym_id', $gym->id)->where('status', 'released')->first();
            $this->assertNotNull($released);
            $this->assertGreaterThan(0, DB::table('payroll_lines')->where('payroll_run_id', $released->id)->count());
            $this->assertSame(2, DB::table('expenses')->where('source_type', 'payroll_run')->where('source_id', $released->id)->count());
            $this->assertSame(3, DB::table('budgets')->where('gym_id', $gym->id)->pluck('month')->unique()->count());
            $this->assertDatabaseHas('budgets', [
                'gym_id' => $gym->id, 'kind' => 'income', 'category' => 'membership',
            ]);
            $this->assertDatabaseHas('budgets', [
                'gym_id' => $gym->id, 'kind' => 'expense', 'category' => 'payroll',
            ]);
            $this->assertDatabaseHas('training_sessions', ['gym_id' => $gym->id, 'type' => 'private_coaching']);
            $this->assertDatabaseHas('training_sessions', ['gym_id' => $gym->id, 'status' => 'cancelled']);
            $this->assertTrue(DB::table('payments')->where('gym_id', $gym->id)->where('amount', '<', 0)->exists());
            foreach (['assessment', 'competition', 'achievement', 'promotion'] as $type) {
                $this->assertDatabaseHas('athlete_entries', ['gym_id' => $gym->id, 'type' => $type]);
            }
            foreach (TrainingSession::where('status', 'scheduled')->get() as $session) {
                $this->assertSame([], app(SessionScheduler::class)->problems(
                    $session->coach_id, $session->room_id, $session->starts_at, $session->ends_at, $session->id,
                ), $session->name.' '.$session->starts_at);
                $this->assertLessThanOrEqual($session->capacity, $session->placesTaken());
                if ($session->bookings()->where('status', 'waitlisted')->exists()) {
                    $this->assertSame($session->capacity, $session->placesTaken());
                }
            }
        }

        $this->assertSame(5, DB::table('statutory_rates')->whereNull('gym_id')->count());
        $this->assertSame(0, DB::table('leave_requests')->whereColumn('decided_at', '<', 'created_at')->count());
        $this->assertSame(0, DB::table('memberships')->whereColumn('decided_at', '<', 'created_at')->count());
        $this->assertSame(0, DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
            ->whereColumn('users.created_at', '>', 'memberships.created_at')->count());
        $this->assertSame(0, DB::table('training_sessions')->where('created_at', '>', now())->count());
        $this->assertSame(0, DB::table('training_sessions')->where('name', 'like', '%Test%')->count());
        foreach (DB::table('users')->pluck('email') as $email) {
            $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL), $email);
        }
        foreach (DB::table('session_bookings')->get() as $booking) {
            $membership = DB::table('memberships')->find($booking->membership_id);
            $session = DB::table('training_sessions')->find($booking->training_session_id);
            $this->assertSame($booking->gym_id, $membership->gym_id);
            $this->assertSame($booking->gym_id, $session->gym_id);
            $this->assertSame($booking->user_id, $membership->user_id);
            if ($booking->check_in_at) {
                $this->assertLessThanOrEqual(now()->toDateTimeString(), $booking->check_in_at);
                $this->assertGreaterThanOrEqual($booking->check_in_at, $booking->check_out_at);
            }
            if (in_array($booking->status, ['booked', 'requested', 'waitlisted']) && $session->starts_at > now()->toDateTimeString()) {
                $this->assertSame('active', $membership->status);
                $this->assertTrue($membership->sessions_remaining === null || $membership->sessions_remaining > 0);
            }
        }
        foreach (DB::table('memberships')->where('status', 'active')->get() as $membership) {
            $this->assertEquals($membership->amount_charged, DB::table('payments')->where('membership_id', $membership->id)->sum('amount'));
            if ($membership->sessions_remaining !== null) {
                $used = DB::table('session_bookings')->where('membership_id', $membership->id)->where('session_pack_used', true)->count();
                $plan = DB::table('membership_plans')->find($membership->membership_plan_id);
                $this->assertSame($plan->session_count - $used, $membership->sessions_remaining);
            }
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(0, app(StockLedger::class)->mismatches());
    }
}
