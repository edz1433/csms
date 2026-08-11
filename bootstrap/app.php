<?php

use App\Http\Middleware\CheckPageAccess;
use App\Http\Middleware\DenyWriteForAccountingStaff;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\MaintenanceGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Maintenance mode is declared in System Settings; administrators pass
        // through so they can keep working while it is on.
        $middleware->web(append: [
            MaintenanceGate::class,
        ]);

        $middleware->alias([
            'page' => CheckPageAccess::class,
            'deny.accounting.write' => DenyWriteForAccountingStaff::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
