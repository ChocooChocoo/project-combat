<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** R-04 password recovery, R-05 own profile, R-06 first-sign-in password. */
class PasswordAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_reply_is_the_same_for_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->json('message');
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->json('message');

        $this->assertSame($known, $unknown);
    }

    public function test_reset_with_code_sets_new_password_and_ends_old_sign_ins(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->createToken('old');
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $code = null;
        Notification::assertSentTo($user, EmailCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return $n->purpose === 'reset';
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'code' => $code, 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'code' => $code, 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        // The code works only once.
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'code' => $code, 'password' => 'Other1234', 'password_confirmation' => 'Other1234',
        ])->assertStatus(422);
    }

    public function test_changing_email_needs_confirming_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/me', [
            'first_name' => 'New', 'last_name' => 'Name', 'phone' => '0917', 'email' => 'new@example.com',
        ])->assertOk()->assertJsonPath('data.email_verified', false);

        Notification::assertSentTo($user, EmailCode::class);
        $this->actingAs($user->fresh())->getJson('/api/notifications')->assertForbidden();
    }

    public function test_changing_password_needs_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'Wrong1234', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'Password123', 'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertOk();
    }

    public function test_owner_created_account_must_set_its_own_password_first(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_change_required' => true])->save();

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertForbidden()->assertJsonPath('password_change_required', true);
        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('data.password_change_required', true);

        $this->actingAs($user)->postJson('/api/me/first-password', [
            'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertJsonValidationErrors('password');

        $this->actingAs($user)->postJson('/api/me/first-password', [
            'password' => 'MyOwn1234', 'password_confirmation' => 'MyOwn1234',
        ])->assertOk();

        $this->actingAs($user->fresh())->getJson('/api/notifications')->assertOk();
    }
}
