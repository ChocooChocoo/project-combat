<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionEndingReminder;
use App\Support\Reminders\SubscriptionReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** R-102 to R-107: platform plans, the free trial, paying through PayMongo, and plan limits. */
class SubscriptionTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private User $admin;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret_key' => 'sk_test_x', 'services.paymongo.webhook_secret' => 'whsk_test']);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
        $this->owner = User::factory()->create();
        $this->gym = Gym::factory()->approved()->create(['trial_ends_at' => now()->addDays(10)]);
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
    }

    private function plan(string $name): SubscriptionPlan
    {
        return SubscriptionPlan::where('name', $name)->firstOrFail();
    }

    private function endTrial(): void
    {
        $this->gym->forceFill(['trial_ends_at' => now()->subMinute()])->save();
    }

    public function test_only_the_admin_manages_plans_and_the_free_plan_stays(): void
    {
        $this->actingAs($this->owner)->getJson('/api/admin/subscription-plans')->assertForbidden();

        $this->actingAs($this->admin)->getJson('/api/admin/subscription-plans')->assertOk()
            ->assertJsonPath('trial_days', 30)
            ->assertJsonPath('plans.0.name', 'Starter')
            ->assertJsonPath('plans.0.is_free', true);

        $this->postJson('/api/admin/subscription-plans', ['name' => 'Gold', 'monthly_price' => 5, 'modules' => ['finance']])
            ->assertUnprocessable()->assertJsonValidationErrors('monthly_price');
        $this->postJson('/api/admin/subscription-plans', ['name' => 'Gold', 'monthly_price' => 999, 'modules' => ['gym_setup']])
            ->assertUnprocessable()->assertJsonValidationErrors('modules.0');
        $id = $this->postJson('/api/admin/subscription-plans', [
            'name' => 'Gold', 'monthly_price' => 999, 'inclusions' => 'Finance only', 'modules' => ['finance'],
        ])->assertCreated()->json('plan.id');

        $this->putJson("/api/admin/subscription-plans/{$id}", ['name' => 'Gold', 'monthly_price' => 1200, 'modules' => ['finance', 'payroll']])
            ->assertOk()->assertJsonPath('plan.modules', ['finance', 'payroll']);
        $this->postJson("/api/admin/subscription-plans/{$id}/toggle-retired")->assertOk()->assertJsonPath('plan.retired', true);

        $starter = $this->plan('Starter');
        $this->postJson("/api/admin/subscription-plans/{$starter->id}/toggle-retired")->assertUnprocessable();
        $this->putJson("/api/admin/subscription-plans/{$starter->id}", ['name' => 'Starter', 'monthly_price' => 500, 'modules' => ['training']])
            ->assertOk()->assertJsonPath('plan.monthly_price', '0.00');

        $this->putJson('/api/admin/subscription-settings', ['trial_days' => 14])->assertOk()->assertJsonPath('trial_days', 14);
    }

    public function test_approval_starts_the_trial_with_the_admin_length(): void
    {
        $this->actingAs($this->admin)->putJson('/api/admin/subscription-settings', ['trial_days' => 14])->assertOk();
        $applicant = User::factory()->create(['account_type' => 'owner']);
        $gym = Gym::factory()->application($applicant)->create(['status' => 'submitted', 'checklist' => []]);
        $gym->forceFill(['checklist' => array_fill_keys(\App\Support\Verification::checklistFor(1), true)])->save();

        $this->postJson("/api/admin/applications/{$gym->id}/decision", ['decision' => 'approve'])->assertOk();

        $this->assertTrue($gym->fresh()->trial_ends_at->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()));
    }

    public function test_the_trial_opens_everything_and_the_free_plan_locks_the_rest(): void
    {
        $finance = "/api/gyms/{$this->gym->id}/finance/income";
        $this->actingAs($this->owner)->getJson($finance)->assertOk();

        $this->endTrial();

        $this->getJson($finance)->assertForbidden()->assertJsonPath('message', "This module is not included in your gym's plan. The owner can subscribe to unlock it.");
        $this->getJson("/api/gyms/{$this->gym->id}/sessions")->assertOk();
        $this->getJson("/api/gyms/{$this->gym->id}/setup")->assertOk();

        $me = collect($this->getJson('/api/me')->assertOk()->json('data.gyms') ?? $this->getJson('/api/me')->json('gyms'))->firstWhere('id', $this->gym->id);
        $this->assertSame('free', $me['subscription']['status']);
        $this->assertContains('finance', $me['subscription']['locked_modules']);
        $this->assertNotContains('finance.payments.view', $me['permissions']);
        $this->assertContains('training.sessions.view', $me['permissions']);

        // An employee whose role grants finance is locked out too.
        $clerk = User::factory()->create();
        $this->gym->addPerson($clerk, GymPersonType::Employee);
        app(\App\Support\CurrentGym::class)->set($this->gym);
        $this->makeRole(['finance.payments.view'], 'Finance clerk')->users()->attach($clerk->id);
        $this->actingAs($clerk)->getJson($finance)->assertForbidden();
    }

    public function test_owner_pays_through_paymongo_once_and_gets_the_plan_after_the_trial(): void
    {
        $paid = false;
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_sub', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_sub', 'status' => 'active']]]),
            'api.paymongo.com/v1/checkout_sessions/cs_sub' => function () use (&$paid) {
                return Http::response(['data' => ['id' => 'cs_sub', 'attributes' => ['status' => 'active', 'checkout_url' => 'https://checkout.paymongo.com/cs_sub',
                    'payments' => $paid ? [['id' => 'pay_sub', 'attributes' => ['status' => 'paid', 'amount' => 149900]]] : []]]]);
            },
        ]);
        $pro = $this->plan('Pro');
        $base = "/api/gyms/{$this->gym->id}/subscription";

        $staff = User::factory()->create();
        $this->gym->addPerson($staff, GymPersonType::Employee);
        $this->actingAs($staff)->getJson($base)->assertForbidden();

        $this->actingAs($this->owner)->getJson($base)->assertOk()->assertJsonPath('subscription.status', 'trial');
        $this->postJson("{$base}/checkout", ['plan_id' => $this->plan('Starter')->id])->assertUnprocessable();
        $this->postJson("{$base}/checkout", ['plan_id' => $pro->id])->assertOk()->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_sub');
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['data']['attributes']['line_items'][0]['amount'] === 149900);
        $this->postJson("{$base}/confirm")->assertOk()->assertJsonPath('paid', false);

        $paid = true;
        $body = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => ['id' => 'cs_sub']]]]);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$body}", 'whsk_test');
        $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$time},te={$signature},li="], $body)->assertOk();
        $this->actingAs($this->owner)->postJson("{$base}/confirm")->assertOk();

        $this->assertSame(1, SubscriptionPayment::where('gym_id', $this->gym->id)->count());
        $gym = $this->gym->fresh();
        // The paid month starts when the trial ends.
        $this->assertTrue($gym->paid_until->eq($gym->trial_ends_at->copy()->addMonthNoOverflow()));

        $this->endTrial();
        $this->getJson($base)->assertOk()->assertJsonPath('subscription.status', 'paid')->assertJsonPath('subscription.plan.name', 'Pro');
        $this->getJson("/api/gyms/{$this->gym->id}/finance/income")->assertOk();
        $this->getJson("/api/gyms/{$this->gym->id}/decision-support/options")->assertForbidden();

        // Q-27: the day the paid month ends, the gym is back on the free plan.
        $gym->forceFill(['paid_until' => now()->subMinute()])->save();
        $this->getJson($base)->assertJsonPath('subscription.status', 'free');
    }

    public function test_routes_checked_inside_controllers_follow_the_plan_too(): void
    {
        $staff = User::factory()->create();
        $this->gym->addPerson($staff, GymPersonType::Employee);
        $this->actingAs($staff)->getJson("/api/gyms/{$this->gym->id}/my-work")->assertOk();

        $this->endTrial(); // Starter: HR and Payroll are locked.

        $locked = "This module is not included in your gym's plan. The owner can subscribe to unlock it.";
        foreach (['my-work', 'my-attendance', 'leave-requests', 'leave-balances'] as $path) {
            $this->getJson("/api/gyms/{$this->gym->id}/{$path}")->assertForbidden()->assertJsonPath('message', $locked);
        }
        $this->postJson("/api/gyms/{$this->gym->id}/my-attendance/clock-in")->assertForbidden();
        $this->postJson("/api/gyms/{$this->gym->id}/my-overtime-requests", [])->assertForbidden();
        $this->actingAs($this->owner)->putJson("/api/gyms/{$this->gym->id}/payroll/settings", ['staff_grace_minutes' => 15])
            ->assertForbidden()->assertJsonPath('message', $locked);
        $this->getJson("/api/gyms/{$this->gym->id}/roles")->assertForbidden();
        $this->getJson("/api/gyms/{$this->gym->id}/holidays")->assertOk();
    }

    public function test_owners_get_one_reminder_before_the_period_ends(): void
    {
        Notification::fake();
        $this->gym->forceFill(['trial_ends_at' => now()->addDays(2)])->save();

        $this->assertSame(1, app(SubscriptionReminders::class)->send());
        $this->assertSame(0, app(SubscriptionReminders::class)->send());
        Notification::assertSentToTimes($this->owner, SubscriptionEndingReminder::class, 1);
    }

    public function test_gyms_without_a_trial_date_are_not_limited(): void
    {
        $gym = Gym::factory()->approved()->create();
        $gym->addPerson($this->owner, GymPersonType::Owner);

        $this->actingAs($this->owner)->getJson("/api/gyms/{$gym->id}/finance/income")->assertOk();
    }
}
