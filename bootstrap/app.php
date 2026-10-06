<?php

use App\Http\Middleware\RecordUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
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
        $middleware->api(append: [RecordUserActivity::class]);
        // A signed-in admin who opens /login (or any guest-only page) lands on the dashboard.
        $middleware->redirectUsersTo(fn () => Auth::guard('admin')->check() ? route('admin.dashboard') : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A failed validation flashes the submitted input back into the session; keep storage secrets out of it.
        $exceptions->dontFlash(['s3_secret', 'rustfs_secret', 'minio_secret']);
    })->create();
