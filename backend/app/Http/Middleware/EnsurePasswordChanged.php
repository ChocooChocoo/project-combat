<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks everything until an owner-created account sets its own password (R-06). */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->password_change_required) {
            return response()->json([
                'message' => 'You must set a new password before continuing.',
                'password_change_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
