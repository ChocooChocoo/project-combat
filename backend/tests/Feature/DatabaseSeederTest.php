<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\TrainingSession;
use App\Notifications\AnnouncementPublished;
use App\Notifications\PlatformNoticeSent;
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

        $anthony = DB::table('users')->where('first_name', 'Anthony')->where('last_name', 'Padilla')->value('id');
        $this->assertSame('applied', DB::table('memberships')->where('gym_id', Gym::orderBy('id')->value('id'))->where('user_id', $anthony)->value('status'));

        foreach (DB::table('gyms')->whereNotNull('applicant_id')->pluck('applicant_id')->unique() as $applicantId) {
            $this->assertSame('owner', DB::table('users')->where('id', $applicantId)->value('account_type'));
        }
        foreach (DB::table('gym_user')->where('role', 'owner')->pluck('user_id')->unique() as $ownerId) {
            $this->assertSame('owner', DB::table('users')->where('id', $ownerId)->value('account_type'));
        }
        foreach (DB::table('gym_user')->where('role', 'employee')->pluck('user_id')->unique() as $employeeId) {
            $this->assertSame('member', DB::table('users')->where('id', $employeeId)->value('account_type'));
        }
        $this->assertSame('owner', DB::table('users')->where('email', 'ramon.villanueva@ironwillboxing.ph')->value('account_type'));
        $this->assertSame('owner', DB::table('users')->where('email', 'jomar.aquino@southsidemuaythai.ph')->value('account_type'));
        $this->assertSame('member', DB::table('users')->where('email', 'sofia.ramirez@demo.combatplatform.ph')->value('account_type'));

        $this->assertDatabaseCount('platform_notices', 2);
        $notice = DB::table('platform_notices')->where('audience', 'owners')->first();
        $this->assertSame('owners', $notice->audience);
        $this->assertSame(5, $notice->recipients_count);
        $notifications = DB::table('notifications')->where('type', PlatformNoticeSent::class)->get()
            ->filter(fn ($notification) => json_decode($notification->data, true)['notice_id'] === $notice->id);
        $this->assertCount(5, $notifications);
        foreach ($notifications as $notification) {
            $payload = json_decode($notification->data, true);
            $this->assertSame($notice->id, $payload['notice_id']);
            $this->assertEqualsCanonicalizing(['title', 'message', 'notice_id'], array_keys($payload));
        }

        $this->assertGreaterThan(0, DB::table('inactivity_notices')->count());
        $this->assertSame(5, DB::table('notifications')->where('data', 'like', '%safety check%due%')->count());

        foreach (Gym::where('status', 'approved')->get() as $gym) {
            app(CurrentGym::class)->set($gym);
            $announcements = DB::table('announcements')->where('gym_id', $gym->id)->get();
            $this->assertCount(3, $announcements);
            $this->assertSame(['all_members', 'customer_group', 'session'], $announcements->pluck('audience_type')->sort()->values()->all());
            $this->assertSame(['announcement', 'announcement', 'event'], $announcements->pluck('kind')->sort()->values()->all());
            foreach ($announcements as $announcement) {
                $this->assertGreaterThan(0, $announcement->recipients_count);
                $this->assertGreaterThanOrEqual(0, $announcement->skipped_count);
                $this->assertSame($announcement->recipients_count, DB::table('notifications')
                    ->where('type', AnnouncementPublished::class)->get()
                    ->filter(fn ($notification) => json_decode($notification->data, true)['announcement_id'] === $announcement->id)->count());
            }
            $this->assertGreaterThan(0, DB::table('stock_items')->where('gym_id', $gym->id)->count());
            $this->assertGreaterThan(0, DB::table('stock_movements')->where('gym_id', $gym->id)->count());
            $this->assertSame(3, DB::table('suppliers')->where('gym_id', $gym->id)->count());
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
            // P09: items waiting for a decision, safety records and a free payroll period.
            foreach (['pending', 'approved', 'rejected', 'archived'] as $status) {
                $this->assertDatabaseHas('offers', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['pending', 'approved', 'rejected', 'withdrawn'] as $status) {
                $this->assertDatabaseHas('coach_applications', ['gym_id' => $gym->id, 'status' => $status]);
            }
            // The approved coach holds the gym's Coach role.
            $approvedCoach = DB::table('coach_applications')->where('gym_id', $gym->id)->where('status', 'approved')->value('user_id');
            $this->assertTrue(DB::table('gym_role_user')->join('gym_roles', 'gym_roles.id', '=', 'gym_role_user.gym_role_id')
                ->where('gym_roles.gym_id', $gym->id)->where('gym_roles.name', 'Coach')->where('gym_role_user.user_id', $approvedCoach)->exists());
            $this->assertSame(2, DB::table('purchase_requests')->where('gym_id', $gym->id)->where('status', 'pending')->count());
            foreach (['image/png', 'application/pdf'] as $mime) {
                $this->assertSame(1, DB::table('purchase_order_proofs')->where('gym_id', $gym->id)->where('mime', $mime)->count());
            }
            foreach (DB::table('purchase_order_proofs')->where('gym_id', $gym->id)->pluck('path') as $path) {
                Storage::disk('local')->assertExists($path);
            }
            foreach (['active', 'achieved', 'dropped'] as $status) {
                $this->assertDatabaseHas('training_goals', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['active', 'recovering', 'cleared'] as $status) {
                $this->assertDatabaseHas('injuries', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['needs_repair', 'unsafe'] as $result) {
                $this->assertDatabaseHas('stock_items', ['gym_id' => $gym->id, 'safety_status' => $result]);
            }
            $this->assertGreaterThan(0, DB::table('equipment_checks')->where('gym_id', $gym->id)->count());
            foreach (['pass', 'needs_repair', 'unsafe'] as $result) {
                $this->assertDatabaseHas('equipment_checks', ['gym_id' => $gym->id, 'result' => $result]);
            }
            $this->assertDatabaseMissing('equipment_checks', ['gym_id' => $gym->id, 'result' => 'ok']);
            // P10: thumbnail, encrypted government IDs, clock-in location.
            $thumb = DB::table('training_sessions')->where('gym_id', $gym->id)->whereNotNull('thumbnail_path')->value('thumbnail_path');
            $this->assertNotNull($thumb);
            $this->assertTrue(DB::table('training_sessions')->where('gym_id', $gym->id)->whereNotNull('thumbnail_path')->where('starts_at', '>=', now())->exists());
            Storage::disk('public')->assertExists($thumb);
            $rawSss = DB::table('employee_profiles')->where('gym_id', $gym->id)->whereNotNull('sss_number')->value('sss_number');
            $this->assertNotNull($rawSss);
            $this->assertDoesNotMatchRegularExpression('/^\d{10}$/', $rawSss);
            if ($gym->id === 1) {
                $this->assertSame(150, (int) DB::table('gyms')->where('id', 1)->value('attendance_radius_m'));
                $this->assertTrue(DB::table('staff_attendance')->where('gym_id', 1)->where('source', 'self')->whereBetween('clock_in_distance_m', [1, 150])->exists());
            }
            // P11: gym media, medical clearance, minor, sparring.
            $cover = DB::table('gyms')->where('id', $gym->id)->value('cover_photo_path');
            $this->assertNotNull($cover);
            Storage::disk('public')->assertExists($cover);
            $this->assertSame(6, DB::table('gym_files')->where('gym_id', $gym->id)->where('kind', 'photo')->count());
            $this->assertSame([1 => 3, 2 => 2, 3 => 1][$gym->id] ?? 0, DB::table('gym_files')->where('gym_id', $gym->id)->where('kind', 'policy')->count());
            $this->assertSame(0, DB::table('memberships')->where('gym_id', $gym->id)->whereNull('medical_file_path')->count());
            Storage::disk('local')->assertExists(DB::table('memberships')->where('gym_id', $gym->id)->value('medical_file_path'));
            if (DB::table('gym_files')->where('gym_id', $gym->id)->where('kind', 'policy')->exists()) {
                foreach (DB::table('memberships')->where('gym_id', $gym->id)->pluck('waivers') as $waivers) {
                    $this->assertContains('gym_terms', json_decode($waivers, true));
                }
            }
            $this->assertTrue(DB::table('athlete_profiles')->where('gym_id', $gym->id)->whereNotNull('weight_kg')->exists());
            $this->assertTrue(DB::table('athlete_profiles')->where('gym_id', $gym->id)->whereNull('weight_kg')->exists());
            $minors = DB::table('users')->join('memberships', 'memberships.user_id', '=', 'users.id')->where('memberships.gym_id', $gym->id)
                ->whereNotNull('users.guardian_consent_at')->where('users.birthdate', '>', today()->subYears(18)->toDateString())->count();
            $this->assertSame(1, $minors);
            // P11: hiring and walk-ins.
            foreach (['open', 'closed'] as $status) {
                $this->assertDatabaseHas('job_postings', ['gym_id' => $gym->id, 'status' => $status]);
            }
            foreach (['pending', 'approved', 'rejected'] as $status) {
                $this->assertDatabaseHas('job_applications', ['gym_id' => $gym->id, 'status' => $status]);
            }
            $approved = DB::table('job_applications')->where('gym_id', $gym->id)->where('status', 'approved')->first();
            $postingTitle = DB::table('job_postings')->where('id', $approved->job_posting_id)->value('title');
            $this->assertSame($postingTitle, DB::table('employee_profiles')->where('user_id', $approved->user_id)->value('job_title'));
            $this->assertDatabaseHas('gym_user', ['gym_id' => $gym->id, 'user_id' => $approved->user_id, 'role' => 'employee']);
            Storage::disk('local')->assertExists($approved->credentials_path);
            $this->assertSame(3, DB::table('walk_ins')->where('gym_id', $gym->id)->whereDate('checked_in_at', today()->toDateString())->count());
            $this->assertTrue(DB::table('walk_ins')->where('gym_id', $gym->id)->whereNotNull('payment_id')->exists());
            $this->assertTrue(DB::table('walk_ins')->where('gym_id', $gym->id)->whereNull('payment_id')->exists());
            $this->assertGreaterThanOrEqual(3, DB::table('payments')->where('gym_id', $gym->id)->where('income_type', 'walk_in')->count());
            $this->assertSame(0, DB::table('memberships')->where('gym_id', $gym->id)->whereIn('status', ['active', 'awaiting_payment', 'frozen'])->whereNull('locked_fee')->count());
            $lastRun = DB::table('payroll_runs')->where('gym_id', $gym->id)->min('starts_on');
            $freeStart = Carbon::parse($lastRun)->subMonthNoOverflow()->startOfMonth();
            $this->assertSame(0, DB::table('payroll_runs')->where('gym_id', $gym->id)->where('starts_on', '<=', $freeStart->copy()->endOfMonth())->count());
            $this->assertGreaterThan(0, DB::table('staff_attendance')->where('gym_id', $gym->id)->whereBetween('work_date', [$freeStart->toDateString(), $freeStart->copy()->endOfMonth()->toDateString()])->count());
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

        $dti = DB::table('gym_documents')->where('type', 'dti')->where('mime', 'image/png')->first();
        $this->assertNotNull($dti);
        Storage::disk('local')->assertExists($dti->path);

        // P11: identities and document review.
        $this->assertSame(0, DB::table('users')->whereNull('birthdate')->count());
        $this->assertGreaterThan(0, DB::table('users')->whereNotNull('middle_name')->count());
        $this->assertTrue(DB::table('users')->where('suffix', 'Jr.')->exists());
        $this->assertSame(0, DB::table('users')->where('account_type', 'owner')->where('birthdate', '>', today()->subYears(18)->toDateString())->count());
        $this->assertSame(0, DB::table('gym_documents')->join('gyms', 'gyms.id', '=', 'gym_documents.gym_id')
            ->where('gyms.status', 'approved')->where('gym_documents.type', '!=', 'facility_photo')->whereNull('gym_documents.admin_viewed_at')->count());
        $submitted = DB::table('gyms')->where('status', 'submitted')->value('id');
        $this->assertTrue(DB::table('gym_documents')->where('gym_id', $submitted)->whereNull('admin_viewed_at')->where('type', '!=', 'facility_photo')->exists());
        $returned = DB::table('gyms')->where('status', 'returned')->value('id');
        $this->assertLessThan(today()->toDateString(), DB::table('gym_documents')->where('gym_id', $returned)->where('type', 'mayors_permit')->value('expires_on'));
        $this->assertTrue(DB::table('gym_documents')->where('ocr_flags', 'like', '%gym_name%')->exists());
        $this->assertTrue(DB::table('gym_documents')->where('ocr_flags', 'like', '%owner_name%')->exists());

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
