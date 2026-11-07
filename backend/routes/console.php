<?php

use App\Enums\BookingStatus;
use App\Enums\GymStatus;
use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\SessionBooking;
use App\Models\User;
use App\Support\MembershipLedger;
use App\Support\PermissionCatalog;
use App\Support\Reminders\FollowUpReminders;
use App\Support\Reminders\MembershipExpiryReminders;
use App\Support\Reminders\SessionReminders;
use App\Support\StaffAbsenceMarker;
use App\Support\StockLedger;
use App\Support\SystemRoles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:sync-permissions', function () {
    PermissionCatalog::sync();
    $this->info('Permission catalog is up to date.');
})->purpose('Write system permission keys to the catalog');

Artisan::command('app:seed-system-roles', function () {
    Gym::where('status', GymStatus::Approved)->each(fn (Gym $gym) => SystemRoles::seed($gym));
    $this->info('Starter roles are in place.');
})->purpose('Create starter roles for approved gyms');

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

Artisan::command('app:send-session-reminders', function (SessionReminders $reminders) {
    $this->info($reminders->send().' session reminders sent.');
})->purpose('Remind booked athletes before their sessions');
Schedule::command('app:send-session-reminders')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('app:send-membership-reminders', function (MembershipExpiryReminders $reminders) {
    $this->info($reminders->send().' membership reminders sent.');
})->purpose('Remind active members before membership expiry');
Schedule::command('app:send-membership-reminders')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('app:send-follow-up-reminders', function (FollowUpReminders $reminders) {
    $this->info($reminders->send().' follow-up reminders sent.');
})->purpose('Remind assignees about due customer follow-ups');
Schedule::command('app:send-follow-up-reminders')->everyFifteenMinutes()->withoutOverlapping();

// R-54: all gyms, including when no request has selected a CurrentGym.
Artisan::command('app:mark-no-shows', function () {
    $count = SessionBooking::withoutGlobalScope('gym')->where('status', BookingStatus::Booked)
        ->whereNull('check_in_at')->whereNull('attendance')
        ->whereHas('session', fn ($q) => $q->withoutGlobalScope('gym')->where('status', 'scheduled')->where('ends_at', '<', now()))
        ->update(['attendance' => 'no_show']);
    $this->info("{$count} no-shows marked.");
})->purpose('Mark ended, unchecked bookings as no-shows');
Schedule::command('app:mark-no-shows')->everyFifteenMinutes();

// R-56: a working day with no row, no approved leave and no holiday becomes absent.
Artisan::command('app:mark-staff-absences {--date=} {--from=} {--to=}', function () {
    $date = $this->option('date');
    $from = $this->option('from');
    $to = $this->option('to');

    if ($date) {
        $start = $end = Carbon::parse($date);
    } elseif ($from || $to) {
        $start = Carbon::parse($from ?? $to);
        $end = Carbon::parse($to ?? $from);
    } else {
        $start = $end = today()->subDay();
    }

    if ($end->lessThan($start)) {
        $this->error('The end date must be on or after the start date.');

        return 1;
    }

    $count = app(StaffAbsenceMarker::class)->mark($start->copy()->startOfDay(), $end->copy()->startOfDay());
    $this->info("{$count} absences marked.");
})->purpose('Mark missing staff attendance as absent');
Schedule::command('app:mark-staff-absences')->dailyAt('00:20');

Artisan::command('app:check-stock-counts', function (StockLedger $ledger) {
    $rows = $ledger->mismatches();
    if ($rows->isEmpty()) {
        $this->info('Every stock count matches its movements.');

        return 0;
    }
    $this->table(['Item', 'Gym', 'Name', 'Shown', 'Movements'],
        $rows->map(fn ($row) => [$row->id, $row->gym_id, $row->name, $row->quantity, $row->movement_total])->all());
    $this->error("{$rows->count()} stock counts differ from their movements. Nothing was changed.");

    return 1;
})->purpose('Report stock items whose quantity differs from their movements');

// Expired sign-in passes are removed daily (R-03).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
