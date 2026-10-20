<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseStandardAlpineForAdmin
{
    private const PREVIOUS_CSP_SAFE_ATTRIBUTE = 'livewire.csp_safe.before_admin';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            self::PREVIOUS_CSP_SAFE_ATTRIBUTE,
            (bool) config('livewire.csp_safe', false),
        );
        config(['livewire.csp_safe' => false]);

        return $next($request);
    }

    /** Restore the setting after Livewire injects its scripts on RequestHandled. */
    public function terminate(Request $request, Response $response): void
    {
        config([
            'livewire.csp_safe' => (bool) $request->attributes->get(self::PREVIOUS_CSP_SAFE_ATTRIBUTE, false),
        ]);
    }
}
