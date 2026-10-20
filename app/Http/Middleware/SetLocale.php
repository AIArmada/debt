<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession()
            ? $request->session()->get('locale')
            : null;

        if (! in_array($locale, ['en', 'ms'], true)) {
            $locale = $request->user()?->locale;
        }

        if (! in_array($locale, ['en', 'ms'], true)) {
            $locale = config('app.locale');
        }

        if (! in_array($locale, ['en', 'ms'], true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
