<?php

namespace Tests\Feature;

use App\Enums;
use App\Models\Gym;
use App\Support\CurrentGym;
use App\Support\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoStatusCoverageTest extends TestCase
{
    use RefreshDatabase;

    public static function dates(): array
    {
        return [['2026-11-05'], ['2026-11-10'], ['2027-04-12'], ['2028-01-03']];
    }

    #[DataProvider('dates')]
    public function test_each_operating_gym_has_realistic_records_for_every_supported_state(string $date): void
    {
        Http::preventStrayRequests();
        Storage::fake('public');
        Storage::fake('local');
        $this->travelTo(Carbon::parse($date)->setTime(10, 0));
        $this->seed();

        $coverage = [
            ['leave_requests', 'status', Enums\LeaveRequestStatus::class],
            ['memberships', 'status', Enums\MembershipStatus::class],
            ['session_bookings', 'status', Enums\BookingStatus::class],
            ['training_sessions', 'status', Enums\SessionStatus::class],
            ['staff_attendance', 'status', Enums\AttendanceStatus::class],
            ['staff_attendance', 'source', Enums\AttendanceSource::class],
            ['overtime_requests', 'status', Enums\OvertimeStatus::class],
            ['overtime_requests', 'kind', Enums\OvertimeKind::class],
            ['performance_notes', 'kind', Enums\PerformanceNoteKind::class],
            ['payroll_runs', 'status', Enums\PayrollStatus::class],
            ['stock_items', 'status', Enums\StockItemStatus::class],
            ['stock_movements', 'type', Enums\StockMovementType::class],
            ['suppliers', 'status', Enums\SupplierStatus::class],
            ['purchase_requests', 'status', Enums\PurchaseRequestStatus::class],
            ['purchase_orders', 'status', Enums\PurchaseOrderStatus::class],
            ['supply_needs', 'status', Enums\SupplyNeedStatus::class],
            ['employee_profiles', 'employment_type', Enums\EmploymentType::class],
            ['employee_profiles', 'pay_basis', Enums\PayBasis::class],
            ['customer_follow_ups', 'status', Enums\CustomerFollowUpStatus::class],
            ['customers', 'status', Enums\CustomerStatus::class],
            ['customers', 'source', Enums\CustomerSource::class],
            ['customer_interactions', 'kind', Enums\CustomerInteractionKind::class],
            ['customer_interactions', 'channel', Enums\CustomerInteractionChannel::class],
            ['announcements', 'kind', Enums\AnnouncementKind::class],
            ['announcements', 'audience_type', Enums\AnnouncementAudience::class],
        ];

        foreach (Gym::where('status', 'approved')->get() as $gym) {
            app(CurrentGym::class)->set($gym);
            foreach ($coverage as [$table, $column, $enum]) {
                foreach ($enum::cases() as $case) {
                    $this->assertTrue(DB::table($table)->where('gym_id', $gym->id)->where($column, $case->value)->exists(),
                        "{$gym->name}: missing {$table}.{$column}={$case->value}");
                }
            }
            foreach (['present', 'late', 'absent', 'no_show'] as $attendance) {
                $this->assertDatabaseHas('session_bookings', ['gym_id' => $gym->id, 'attendance' => $attendance]);
            }
            foreach (['account', 'qr'] as $method) {
                $this->assertDatabaseHas('session_bookings', ['gym_id' => $gym->id, 'attendance_method' => $method]);
            }
            foreach (array_keys(config('finance.income_types')) as $type) {
                $this->assertDatabaseHas('payments', ['gym_id' => $gym->id, 'income_type' => $type]);
            }
            foreach (Enums\PaymentMethod::cases() as $method) {
                $this->assertDatabaseHas('payments', ['gym_id' => $gym->id, 'method' => $method->value]);
            }
            foreach (['unpaid', 'partial', 'paid'] as $balance) {
                $orders = $gym->purchaseOrders()->with('supplier')->get();
                $this->assertNotEmpty($orders->filter(function ($order) use ($balance) {
                    $total = (float) $order->receipts()->sum('total');
                    $paid = (float) $order->payments()->sum('amount');

                    return $total > 0 && match ($balance) {
                        'unpaid' => $paid === 0.0,
                        'partial' => $paid > 0 && $paid < $total,
                        'paid' => $paid === $total,
                    };
                }), "{$gym->name}: missing {$balance} payable");
            }
            $this->assertDatabaseHas('supply_needs', ['gym_id' => $gym->id, 'status' => 'requested']);
            $this->assertGreaterThan(0, DB::table('staff_attendance_changes')->join('staff_attendance', 'staff_attendance.id', '=', 'staff_attendance_changes.staff_attendance_id')->where('staff_attendance.gym_id', $gym->id)->count());
            $this->assertDatabaseHas('payroll_runs', ['gym_id' => $gym->id, 'round' => 2]);
            $this->assertDatabaseHas('stock_items', ['gym_id' => $gym->id, 'quantity' => 0, 'status' => 'active']);
        }

        foreach (Enums\GymStatus::cases() as $status) {
            $this->assertDatabaseHas('gyms', ['status' => $status->value]);
        }
        foreach (Enums\PayrollStep::cases() as $step) {
            $this->assertDatabaseHas('payroll_approvals', ['step_key' => $step->value, 'decision' => 'approve']);
        }
        foreach (Enums\PlatformNoticeAudience::cases() as $audience) {
            $this->assertDatabaseHas('platform_notices', ['audience' => $audience->value]);
        }
        $states = Gym::where('status', 'approved')->get()->map(fn ($gym) => Subscription::state($gym)['status'])->unique()->all();
        foreach (['unmanaged', 'trial', 'paid', 'free'] as $state) {
            $this->assertContains($state, $states);
        }

        $this->assertSame(0, DB::table('users')->where('email', 'like', '%.test')->count());
        $this->assertSame(0, DB::table('memberships')->where('health_notes', 'like', '%demo%')->count());
        $contacts = DB::table('memberships')->pluck('emergency_contacts')->map(fn ($value) => json_decode($value, true)[0]);
        $this->assertGreaterThan(30, $contacts->pluck('name')->unique()->count());
        $this->assertGreaterThan(30, $contacts->pluck('phone')->unique()->count());
        $this->assertSame(0, DB::table('leave_requests')->where('status', 'pending')->where('starts_on', '<=', today())->count());
        $this->assertSame(0, DB::table('gym_holidays')->whereYear('date', '<', today()->year)->count());
        $this->assertSame(0, DB::table('overtime_requests')->whereColumn('decided_at', '<', 'created_at')->count());
        $this->assertSame(0, DB::table('staff_attendance')->where('clock_out_at', '>', now())->count());
    }
}
