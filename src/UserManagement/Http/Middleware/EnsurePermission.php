<?php

namespace Src\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('admin');

        abort_unless($user && $user->hasPermission($permission), 403);

        return $next($request);
    }
}
