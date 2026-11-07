<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Member-only or owner-only screens, chosen at sign-up and fixed for the life of the account (R-01, R-07). */
class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        // Platform admin review sits outside the account-type split (plan: platform admin unaffected).
        if ($request->user()?->is_platform_admin) {
            return $next($request);
        }

        $required = AccountType::tryFrom($type);

        abort_unless(
            $required !== null && $request->user()?->account_type === $required,
            403,
            $required === AccountType::Owner
                ? 'Only Gym Owner accounts can do this.'
                : 'Only Gym Member accounts can do this.',
        );

        return $next($request);
    }
}
