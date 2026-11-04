<?php

namespace App\Providers;

use App\Support\CurrentGym;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One current gym per request (R-09).
        $this->app->scoped(CurrentGym::class);
    }

    public function boot(): void
    {
        // One password rule everywhere: sign-up, reset, change (R-01, R-04, R-05).
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        RateLimiter::for('auth-public', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('geo', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
