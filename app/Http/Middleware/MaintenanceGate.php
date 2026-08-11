<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-level maintenance mode, declared from System Settings.
 *
 * Unlike `php artisan down` this keeps the app running for administrators, so
 * whoever declared the maintenance can still sign in, finish the work and lift
 * it again. Everyone else gets the maintenance page (503) — including guests,
 * who can still reach the login screen so an administrator can get in.
 */
class MaintenanceGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::bool('maintenance_enabled')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->isAdministrator() || $request->routeIs('login', 'logout')) {
            return $next($request);
        }

        $payload = [
            'message' => Setting::get('maintenance_message') ?: 'The system is temporarily unavailable while we perform scheduled maintenance.',
            'until' => Setting::get('maintenance_until'),
        ];

        if ($request->expectsJson()) {
            return response()->json(['maintenance' => true] + $payload, 503);
        }

        return response()->view('maintenance', $payload, 503);
    }
}
