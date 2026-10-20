<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableAuthzImpersonationRoutes
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->is('authz/leave-impersonation', 'filament-authz/impersonate/*'),
            404,
        );

        return $next($request);
    }
}
