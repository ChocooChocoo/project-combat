<?php

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Support\MembershipLedger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// R-07: the only way to create a platform administrator. No screen or request can do it.
Artisan::command('app:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error('No account with that email. Ask the person to sign up first.');

        return 1;
    }

    $user->forceFill(['is_platform_admin' => true])->save();
    $this->info("{$email} is now a platform administrator.");
})->purpose('Make an existing account a platform administrator');

// R-36: memberships stop being active after their end date, and freezes end on time.
Artisan::command('app:expire-memberships', function (MembershipLedger $ledger) {
    $expired = 0;
    $unfrozen = 0;

    // Every gym at once, on purpose: this job runs outside any request.
    Membership::withoutGlobalScope('gym')->with(['gym', 'user'])
        ->where('status', MembershipStatus::Frozen->value)
        ->whereDate('frozen_until', '<', today())
        ->each(function (Membership $membership) use ($ledger, &$unfrozen) {
            $membership->status = MembershipStatus::Active;
            $membership->frozen_until = null;
            $membership->save();
            $ledger->log($membership, 'unfrozen', null, $ledger->periodNote($membership));
            $unfrozen++;
        });

    Membership::withoutGlobalScope('gym')->with(['gym', 'user'])
        ->where('status', MembershipStatus::Active->value)
        ->where(fn ($q) => $q->whereDate('ends_on', '<', today())->orWhere('sessions_remaining', 0))
        ->each(function (Membership $membership) use ($ledger, &$expired) {
            $membership->status = MembershipStatus::Expired;
            $membership->save();
            $ledger->log($membership, 'expired', null, $membership->ends_on ? 'Ended '.$membership->ends_on->toDateString() : 'No sessions left');
            $expired++;
        });

    $this->info("{$expired} expired, {$unfrozen} unfrozen.");
})->purpose('Expire memberships past their end date and end finished freezes');

Schedule::command('app:expire-memberships')->dailyAt('00:05');

// Expired sign-in passes are removed daily (R-03).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
