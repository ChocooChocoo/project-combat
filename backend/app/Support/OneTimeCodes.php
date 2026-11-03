<?php

namespace App\Support;

use App\Models\OneTimeCode;
use App\Models\User;
use App\Notifications\EmailCode;
use Illuminate\Support\Facades\Hash;

/** Issues and checks emailed codes (R-02, R-04). Codes are stored hashed. */
class OneTimeCodes
{
    public const VERIFY = 'verify';

    public const RESET = 'reset';

    public function issue(User $user, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);

        OneTimeCode::where('user_id', $user->id)->where('purpose', $purpose)->delete();
        OneTimeCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('auth.code_minutes')),
        ]);

        $user->notify(new EmailCode($code, $purpose));
    }

    /** True once, for a correct unexpired code. Wrong tries count; too many burn the code. */
    public function consume(User $user, string $purpose, string $code): bool
    {
        $record = OneTimeCode::where('user_id', $user->id)->where('purpose', $purpose)->first();

        if (! $record || $record->expires_at->isPast() || $record->attempts >= config('auth.code_max_attempts')) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return false;
        }

        $record->delete();

        return true;
    }
}
