<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyWriteForAccountingStaff
{
    /**
     * Accounting Staff is view-only everywhere. This blocks every mutating
     * verb (POST/PUT/PATCH/DELETE) for that role. The single write exception —
     * the payment-status toggle — is NOT wrapped by this middleware, so it
     * stays reachable (see routes/web.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->isAccountingStaff()
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ) {
            abort(403, 'Accounting Staff has view-only access.');
        }

        return $next($request);
    }
}
