<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** R-52: members display a rotating credential; only staff can redeem it. */
class CheckInCode
{
    public function issue(Gym $gym, User $user, ?int $window = null): array
    {
        $window ??= intdiv(now()->timestamp, 60);
        $message = "{$gym->id}.{$user->id}.{$window}";
        $key = config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
        $hmac = hash_hmac('sha256', $message, $key);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $short = '';
        foreach (str_split(substr($hmac, 0, 16), 2) as $byte) {
            $short .= $alphabet[hexdec($byte) % 32];
        }

        return [
            'payload' => $message.'.'.substr($hmac, 0, 32), 'short_code' => $short,
            'expires_at' => now()->setTimestamp(($window + 1) * 60)->toIso8601String(),
        ];
    }

    public function resolve(Gym $gym, string $code): User
    {
        $code = trim($code);
        $window = intdiv(now()->timestamp, 60);
        $members = Membership::where('status', MembershipStatus::Active);
        if (str_contains($code, '.')) {
            $parts = explode('.', $code);
            if (count($parts) !== 4 || ! ctype_digit($parts[1])) {
                $this->invalid();
            }
            $members->where('user_id', $parts[1]);
        }
        // R-52: short-code lookup recomputes credentials for this gym only.
        foreach ($members->with('user')->get()->unique('user_id') as $membership) {
            foreach ([$window, $window - 1] as $candidate) {
                $issued = $this->issue($gym, $membership->user, $candidate);
                if (hash_equals($issued['payload'], $code) || hash_equals($issued['short_code'], strtoupper($code))) {
                    return $membership->user;
                }
            }
        }
        $this->invalid();
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => 'Code expired or not valid. Ask the member to refresh it.']);
    }
}
