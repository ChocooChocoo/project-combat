<?php

namespace App\Http\Middleware;

use App\Support\CurrentGym;
use App\Support\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-106: for routes that check the person inside the controller (own records, owner-only settings)
 * rather than through `permission:`. Refuses the request when the gym's plan leaves the module out.
 */
class RequirePlanModule
{
    public function __construct(private CurrentGym $currentGym) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $gym = $this->currentGym->get();
        abort_if(
            $gym && in_array($module, Subscription::lockedModules($gym), true),
            403,
            "This module is not included in your gym's plan. The owner can subscribe to unlock it.",
        );

        return $next($request);
    }
}
