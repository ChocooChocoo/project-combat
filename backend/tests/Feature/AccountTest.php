<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** R-01 sign-up, R-02 emailed code, R-03 sign-in and sign-out. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function sentCode(User $user, string $purpose = 'verify'): string
    {
        $code = null;
        Notification::assertSentTo($user, EmailCode::class, function (EmailCode $n) use (&$code, $purpose) {
            $code = $n->code;

            return $n->purpose === $purpose;
        });

        return $code;
    }

    private function register(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/register', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'birthdate' => '1995-05-10',
            'account_type' => 'member',
            ...$extra,
        ]);
    }

    public function test_sign_up_creates_a_plain_unconfirmed_platform_user_and_emails_a_code(): void
    {
        Notification::fake();

        $this->register(['is_platform_admin' => true])->assertCreated();

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->is_platform_admin);
        $this->assertCount(0, $user->gyms);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentCode($user));
    }

    public function test_email_can_only_be_used_once_and_weak_passwords_are_refused(): void
    {
        Notification::fake();
        $this->register()->assertCreated();

        $this->register()->assertJsonValidationErrors('email');
        $this->register(['email' => 'b@example.com', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertJsonValidationErrors('password');
    }

    public function test_correct_code_confirms_and_signs_in(): void
    {
        Notification::fake();
        $this->register();
        $user = User::firstWhere('email', 'ana@example.com');

        $this->postJson('/api/auth/verify', ['email' => $user->email, 'code' => $this->sentCode($user)])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['email_verified']])
            ->assertJsonPath('user.email_verified', true);
    }

    public function test_code_stops_working_after_too_many_wrong_tries(): void
    {
        Notification::fake();
        $this->register();
        $user = User::firstWhere('email', 'ana@example.com');
        $code = $this->sentCode($user);
        $wrong = $code === '111111' ? '222222' : '111111';

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/verify', ['email' => $user->email, 'code' => $wrong])->assertStatus(422);
        }

        $this->postJson('/api/auth/verify', ['email' => $user->email, 'code' => $code])->assertStatus(422);
    }

    public function test_code_expires(): void
    {
        Notification::fake();
        $this->register();
        $user = User::firstWhere('email', 'ana@example.com');
        $code = $this->sentCode($user);

        $this->travel(11)->minutes();

        $this->postJson('/api/auth/verify', ['email' => $user->email, 'code' => $code])->assertStatus(422);
    }

    public function test_resend_reply_is_the_same_for_unknown_emails(): void
    {
        Notification::fake();
        $this->register();

        $known = $this->postJson('/api/auth/resend-code', ['email' => 'ana@example.com'])->json('message');
        $unknown = $this->postJson('/api/auth/resend-code', ['email' => 'nobody@example.com'])->json('message');

        $this->assertSame($known, $unknown);
    }

    public function test_unconfirmed_accounts_cannot_use_protected_features(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertForbidden()->assertJsonPath('needs_verification', true);

        $this->actingAs($user)->getJson('/api/notifications')->assertForbidden();
    }

    public function test_repeated_wrong_passwords_pause_sign_in(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Wrong1234'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Password123'])->assertStatus(429);
    }

    public function test_sign_out_ends_the_pass_and_passes_expire(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Password123'])->json('token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Password123'])->json('token');
        $this->travel(config('sanctum.expiration') + 1)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }
}
