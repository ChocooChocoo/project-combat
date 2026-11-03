<?php

use App\Models\User;
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

// Expired sign-in passes are removed daily (R-03).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
