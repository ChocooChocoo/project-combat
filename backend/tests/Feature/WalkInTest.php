<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\WalkIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

/** R-137: the public walk-in kiosk and the front desk list. */
class WalkInTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    private function kiosk(string $path = 'walk-ins'): string
    {
        return "/api/public/gyms/{$this->gym->id}/{$path}";
    }

    private function visit(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Pedro Reyes',
            'phone' => '0917 555 0101',
            'email' => 'pedro@example.com',
            'health_answers' => ['recent_injury' => true],
            'waivers' => array_fill_keys(array_keys(config('membership.waivers')), true),
            'signed_name' => 'Pedro Reyes',
        ], $overrides);
    }

    private function row(array $overrides = []): int
    {
        return DB::table('walk_ins')->insertGetId([
            'gym_id' => $this->gym->id, 'name' => 'Visitor', 'phone' => '0917 000 0000',
            'waivers' => '[]', 'signed_name' => 'Visitor', 'checked_in_at' => now(),
            'created_at' => now(), 'updated_at' => now(), ...$overrides,
        ]);
    }

    public function test_a_guest_reads_the_kiosk_form_and_pending_gyms_are_hidden(): void
    {
        $this->getJson($this->kiosk('walk-in'))->assertOk()
            ->assertJsonPath('gym.needs_terms', false)
            ->assertJsonStructure(['gym' => ['id', 'name', 'policy_files'], 'health_questions', 'waivers']);

        $pending = Gym::factory()->create();
        $this->getJson("/api/public/gyms/{$pending->id}/walk-in")->assertNotFound();
        $this->postJson("/api/public/gyms/{$pending->id}/walk-ins", $this->visit())->assertNotFound();
    }

    public function test_a_guest_creates_a_walk_in_and_a_walk_in_customer(): void
    {
        $this->postJson($this->kiosk(), $this->visit())->assertCreated()->assertJsonMissingPath('id');

        $this->assertSame(1, WalkIn::withoutGlobalScope('gym')->count());
        $customer = Customer::withoutGlobalScope('gym')->firstOrFail();
        $this->assertSame('walk_in', $customer->source->value);
        $this->assertSame($this->gym->id, $customer->gym_id);
    }

    public function test_a_second_visit_reuses_the_customer(): void
    {
        $this->postJson($this->kiosk(), $this->visit())->assertCreated();
        $this->postJson($this->kiosk(), $this->visit())->assertCreated();

        $this->assertSame(1, Customer::withoutGlobalScope('gym')->count());
        $this->assertSame(2, WalkIn::withoutGlobalScope('gym')->count());
    }

    public function test_waivers_are_required_and_gym_terms_when_the_gym_has_policies(): void
    {
        $this->postJson($this->kiosk(), $this->visit(['waivers' => ['liability' => false]]))
            ->assertUnprocessable()->assertJsonValidationErrors('waivers.liability');

        $this->gym->forceFill(['policies' => 'No outside shoes.'])->save();
        $this->postJson($this->kiosk(), $this->visit())->assertUnprocessable()->assertJsonValidationErrors('waivers.gym_terms');
        $this->postJson($this->kiosk(), $this->visit(['waivers' => ['gym_terms' => true]]))->assertCreated();
    }

    public function test_posting_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($this->kiosk(), $this->visit(['waivers' => ['liability' => false]]))->assertUnprocessable();
        }

        $this->postJson($this->kiosk(), $this->visit())->assertStatus(429);
    }

    public function test_the_desk_lists_todays_rows_and_hides_health_without_permission(): void
    {
        $this->postJson($this->kiosk(), $this->visit())->assertCreated();
        $this->row(['name' => 'Old', 'checked_in_at' => now()->subDays(2)]);

        $desk = $this->employeeWith(['training.check_ins.manage']);
        $res = $this->actingAs($desk)->getJson($this->url('walk-ins'))->assertOk();
        $res->assertJsonCount(1, 'walk_ins')
            ->assertJsonPath('walk_ins.0.health_flag', true)
            ->assertJsonPath('walk_ins.0.paid', false)
            ->assertJsonMissingPath('walk_ins.0.health_answers');

        $nurse = $this->employeeWith(['training.check_ins.manage', 'health_records.records.view']);
        $this->actingAs($nurse)->getJson($this->url('walk-ins'))
            ->assertJsonPath('walk_ins.0.health_answers.recent_injury', true);
    }

    public function test_the_desk_records_payment_once(): void
    {
        $id = $this->row();
        $cashier = $this->employeeWith(['finance.payments.manage']);
        $body = ['amount' => 100, 'method' => 'cash'];

        $this->actingAs($cashier)->postJson($this->url("walk-ins/{$id}/payment"), $body)
            ->assertOk()->assertJsonPath('walk_in.paid', true);

        $payment = Payment::withoutGlobalScope('gym')->firstOrFail();
        $this->assertSame('walk_in', $payment->income_type);
        $this->assertSame('Visitor', $payment->payer_name);

        $this->actingAs($cashier)->postJson($this->url("walk-ins/{$id}/payment"), $body)->assertStatus(409);
    }

    public function test_payment_needs_permission_and_the_same_gym(): void
    {
        $id = $this->row();
        $body = ['amount' => 100, 'method' => 'cash'];

        $clerk = $this->employeeWith(['training.check_ins.manage']);
        $this->actingAs($clerk)->postJson($this->url("walk-ins/{$id}/payment"), $body)->assertForbidden();

        $other = Gym::factory()->approved()->create();
        $foreign = DB::table('walk_ins')->insertGetId([
            'gym_id' => $other->id, 'name' => 'Far', 'phone' => '0917 000 0000', 'waivers' => '[]',
            'signed_name' => 'Far', 'checked_in_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cashier = $this->employeeWith(['finance.payments.manage']);
        $this->actingAs($cashier)->postJson($this->url("walk-ins/{$foreign}/payment"), $body)->assertNotFound();
    }
}
