<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPageAccess
{
    /**
     * Gate a route group by a page key. Administrators always pass;
     * everyone else must have the key in their access array.
     *
     * Usage: ->middleware('page:receiving')
     */
    public function handle(Request $request, Closure $next, string $pageKey): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        if (! $user->canAccess($pageKey)) {
            abort(403, 'You do not have access to this page.');
        }

        return $next($request);
    }
}
