<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Support\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current gym from the {gym} route part, after checking the person
 * holds a role in it (R-09). Platform administrators get no gym access.
 */
class EnsureGymAccess
{
    public function __construct(private CurrentGym $currentGym) {}

    public function handle(Request $request, Closure $next): Response
    {
        $gym = $request->route('gym');
        $gym = $gym instanceof Gym ? $gym : Gym::find($gym);

        if (! $gym || ! $request->user()->hasGymRole($gym)) {
            abort(404);
        }

        $this->currentGym->set($gym);

        return $next($request);
    }
}
