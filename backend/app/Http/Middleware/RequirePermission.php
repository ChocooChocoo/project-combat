<?php

namespace App\Http\Middleware;

use App\Support\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private CurrentGym $currentGym) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $gym = $this->currentGym->get();
        $user = $request->user();
        if (! $gym || ! $user?->hasPermission($gym, ...$permissions)) {
            // R-106: say so when the role allows it but the gym's plan does not.
            $planLocked = $gym && $user && $user->grantedPermissionsIn($gym->id)->intersect($permissions)->isNotEmpty();
            abort(403, $planLocked
                ? "This module is not included in your gym's plan. The owner can subscribe to unlock it."
                : 'You do not have access to this part of the gym.');
        }

        return $next($request);
    }
}
