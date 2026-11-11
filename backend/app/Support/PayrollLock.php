<?php

namespace App\Support;

use App\Enums\PayrollStatus;
use App\Models\Gym;
use App\Models\PayrollRun;
use Illuminate\Support\Carbon;

/**
 * R-61: a date inside an in-review or released payroll run is closed. Rejection
 * returns the run to draft, which opens the dates again.
 */
final class PayrollLock
{
    public static function assertOpen(Gym $gym, Carbon $date): void
    {
        self::assertOpenRange($gym, $date, $date);
    }

    public static function assertOpenRange(Gym $gym, Carbon $from, Carbon $to): void
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $run = PayrollRun::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereIn('status', [PayrollStatus::InReview->value, PayrollStatus::Released->value])
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->orderBy('id')
            ->first();

        if ($run === null) {
            return;
        }

        $state = $run->status === PayrollStatus::Released ? 'has been released' : 'is waiting for approval';

        abort(409, "This date is in payroll run #{$run->id}, which {$state}.");
    }
}
