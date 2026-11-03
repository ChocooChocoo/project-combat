<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protected features need a confirmed email (R-02). Needed again after an email change (R-05). */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->email_verified_at) {
            return response()->json([
                'message' => 'Confirm your email address first.',
                'needs_verification' => true,
            ], 403);
        }

        return $next($request);
    }
}
