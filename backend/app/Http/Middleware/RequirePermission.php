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
        abort_unless($gym && $request->user()?->hasPermission($gym, ...$permissions), 403, 'You do not have access to this part of the gym.');

        return $next($request);
    }
}
