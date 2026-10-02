<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Src\UserManagement\Http\Middleware\EnsurePermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs behind a TLS-terminating reverse proxy (Dokploy/Traefik): without
        // this the X-Forwarded-Proto header is ignored and asset()/route() URLs
        // come out as http:// on an https:// page (blocked as mixed content).
        $middleware->trustProxies(at: '*');
        $middleware->alias(['permission' => EnsurePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
