<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin,officer')
 * Super Admin always passes.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->isSuperAdmin() || $user->hasRole(...$roles))) {
            abort(403);
        }

        return $next($request);
    }
}
