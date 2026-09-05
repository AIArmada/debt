<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'none'",
                "script-src 'self' 'unsafe-inline'".(app()->isProduction() ? '' : " 'unsafe-eval' http://localhost:5173"),
                "style-src 'self' 'unsafe-inline'".(app()->isProduction() ? '' : ' http://localhost:5173'),
                "img-src 'self' data: blob: https:",
                "font-src 'self' data: https:",
                "connect-src 'self'".(app()->isProduction() ? '' : ' http://localhost:5173 ws://localhost:5173'),
                "form-action 'self'",
            ]),
        );
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
