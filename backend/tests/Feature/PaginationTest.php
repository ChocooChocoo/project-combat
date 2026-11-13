<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BookingSetup;
use Tests\TestCase;

/** R-123: `?page` returns 25 rows plus meta; no `page` returns the whole list with no meta. */
class PaginationTest extends TestCase
{
    use BookingSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBooking();
    }

    public function test_payments_page(): void
    {
        $this->seedPayments();
        $this->assertPages('payments', 'payments', 31); // setUpBooking made one payment
    }

    public function test_payments_page_respects_the_date_range(): void
    {
        $this->seedPayments();
        $this->actingAs($this->owner)->getJson($this->url('payments?from=2026-11-01&to=2026-11-30&page=1'))
            ->assertOk()->assertJsonCount(21, 'payments')->assertJsonPath('meta.total', 21);
    }

    public function test_memberships_page(): void
    {
        $plan = $this->plan->id;
        $this->insertRows('memberships', fn () => [
            'gym_id' => $this->gym->id, 'user_id' => $this->applicant->id, 'membership_plan_id' => $plan, 'status' => 'active',
            'emergency_contacts' => '[]', 'health_answers' => '[]', 'waivers' => '[]', 'signature_name' => 'Ana Cruz', 'signed_on' => '2026-11-01',
        ]);
        // setUpBooking already made one membership, so the gym holds 31.
        $this->assertPages('memberships', 'memberships', 31);
    }

    public function test_bookings_page(): void
    {
        $session = $this->sessionId;
        $this->insertRows('session_bookings', fn () => [
            'gym_id' => $this->gym->id, 'training_session_id' => $session, 'user_id' => User::factory()->create()->id, 'status' => 'cancelled',
        ]);
        $this->assertPages('bookings?status=cancelled', 'bookings');
    }

    public function test_staff_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->gym->addPerson(User::factory()->create(), GymPersonType::Employee);
        }
        $this->assertPages('staff', 'staff', 31); // the coach from setUpBooking is staff too
        $this->actingAs($this->owner)->getJson($this->url('staff?page=1'))->assertJsonPath('owner.id', $this->owner->id);
    }

    public function test_leave_page(): void
    {
        LeaveRequest::factory()->forGym($this->gym)->count(30)->create();
        $this->assertPages('leave-requests', 'requests');
    }

    public function test_overtime_page(): void
    {
        $this->insertRows('overtime_requests', fn () => [
            'gym_id' => $this->gym->id, 'user_id' => $this->coach->id, 'work_date' => '2026-11-03',
            'kind' => 'manual', 'requested_minutes' => 60, 'reason' => 'Busy', 'status' => 'pending',
        ]);
        $this->assertPages('overtime-requests', 'requests');
    }

    public function test_offers_page(): void
    {
        $this->insertRows('offers', fn () => [
            'gym_id' => $this->gym->id, 'kind' => 'discount', 'name' => 'Promo', 'discount_type' => 'fixed', 'value' => 100,
            'starts_on' => '2026-11-01', 'status' => 'pending',
        ]);
        $this->assertPages('offers', 'offers');
        $this->actingAs($this->owner)->getJson($this->url('offers?page=1'))->assertJsonStructure(['plans']);
    }

    public function test_coach_applications_page(): void
    {
        $this->insertRows('coach_applications', fn () => [
            'gym_id' => $this->gym->id, 'user_id' => User::factory()->create()->id, 'status' => 'pending',
            'experience' => 'Five years', 'availability' => '[]',
        ]);
        $this->assertPages('coach-applications', 'applications');
    }

    public function test_page_zero_is_rejected(): void
    {
        foreach (['payments', 'memberships', 'bookings', 'staff', 'leave-requests', 'overtime-requests', 'offers', 'coach-applications'] as $path) {
            $this->actingAs($this->owner)->getJson($this->url("{$path}?page=0"))->assertUnprocessable();
        }
    }

    private function seedPayments(): void
    {
        $i = 0;
        $this->insertRows('payments', function () use (&$i) {
            $i++;

            return [
                'gym_id' => $this->gym->id, 'user_id' => $this->applicant->id, 'amount' => 100, 'method' => 'cash',
                'paid_at' => $i <= 10 ? '2026-10-15 10:00:00' : '2026-11-03 10:00:00',
            ];
        });
    }

    private function insertRows(string $table, callable $row): void
    {
        $now = now()->toDateTimeString();
        DB::table($table)->insert(array_map(fn () => $row() + ['created_at' => $now, 'updated_at' => $now], range(1, 30)));
    }

    private function assertPages(string $path, string $key, int $total = 30): void
    {
        $url = $this->url($path);
        $join = str_contains($url, '?') ? '&' : '?';

        $this->actingAs($this->owner)->getJson("{$url}{$join}page=1")->assertOk()
            ->assertJsonCount(25, $key)->assertJsonPath('meta.total', $total)->assertJsonPath('meta.per_page', 25);
        $this->actingAs($this->owner)->getJson("{$url}{$join}page=2")->assertOk()->assertJsonCount($total - 25, $key);
        $this->actingAs($this->owner)->getJson($url)->assertOk()->assertJsonCount($total, $key)->assertJsonMissingPath('meta');
    }
}
