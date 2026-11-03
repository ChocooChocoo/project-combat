<?php

namespace App\Http\Middleware;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Support\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('module:finance,manage'). Runs after the gym middleware (R-08). */
class RequireModuleAccess
{
    public function __construct(private CurrentGym $currentGym) {}

    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $gym = $this->currentGym->get();

        if (! $gym || ! $request->user()->canInGym($gym, Module::from($module), AccessLevel::from($level))) {
            abort(403, 'You do not have access to this part of the gym.');
        }

        return $next($request);
    }
}
