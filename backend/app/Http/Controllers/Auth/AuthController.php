<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Guardian;
use App\Support\OneTimeCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OneTimeCodes $codes) {}

    /** R-01: one sign-up for everyone. A new account is a plain platform user. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::in(User::SUFFIXES)],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            ...Guardian::rules(),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
        ]);

        $guardian = Guardian::check($data, AccountType::from($data['account_type']));
        $user = User::create(Arr::except($data, ['guardian_name', 'guardian_phone', 'guardian_relationship', 'guardian_consent']));
        $user->forceFill($guardian)->save();
        $this->codes->issue($user, OneTimeCodes::VERIFY);

        return response()->json(['message' => 'Account created. Check your email for a code.'], 201);
    }

    /** R-02: a correct code confirms the account and signs the person in. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->email_verified_at || ! $this->codes->consume($user, OneTimeCodes::VERIFY, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The code is wrong, expired, or used up. Ask for a new one.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $this->signedIn($user);
    }

    public function resendCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->whereNull('email_verified_at')->first();

        if ($user) {
            RateLimiter::attempt('resend:'.$user->id, 1, fn () => $this->codes->issue($user, OneTimeCodes::VERIFY), 60);
        }

        // Same reply either way, so strangers cannot tell which emails have accounts.
        return response()->json(['message' => 'If that account is waiting for confirmation, a new code is on its way.']);
    }

    /** R-03: safe sign-in. Repeated wrong passwords for one email pause sign-in for a minute. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('auth.login_max_attempts'))) {
            return response()->json([
                'message' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds and try again.',
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email or password is wrong.']);
        }

        RateLimiter::clear($key);

        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Confirm your email first.', 'needs_verification' => true], 403);
        }

        return $this->signedIn($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** R-04: same reply whether or not the email has an account. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();

        if ($user) {
            RateLimiter::attempt('reset:'.$user->id, 1, fn () => $this->codes->issue($user, OneTimeCodes::RESET), 60);
        }

        return response()->json(['message' => 'If that email has an account, a reset code is on its way.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $this->codes->consume($user, OneTimeCodes::RESET, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The code is wrong, expired, or used up. Ask for a new one.']);
        }

        // The code proves the email, so confirm it too. A reset ends every existing sign-in.
        $user->forceFill(['password' => $data['password'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password changed. You can sign in now.']);
    }

    /** R-06: an invited employee sets their own password with the emailed code. */
    public function acceptInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $this->codes->consume($user, OneTimeCodes::INVITE, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The code is wrong, expired, or used up. Ask for a new one.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => now(),
            'password_change_required' => false,
        ])->save();
        $user->tokens()->delete();

        return response()->json(['message' => 'Password set. You can sign in now.']);
    }

    private function signedIn(User $user): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('app')->plainTextToken,
            'user' => new UserResource($user->load('gyms')),
        ]);
    }
}
