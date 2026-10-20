<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    public function test_it_uses_the_authenticated_user_locale_without_a_session_store(): void
    {
        config(['app.locale' => 'en']);
        $request = Request::create('/admin');
        $request->setUserResolver(fn (?string $guard = null): object => (object) ['locale' => 'ms']);

        self::assertFalse($request->hasSession());

        $response = app(SetLocale::class)->handle(
            $request,
            fn (Request $request): Response => response()->noContent(),
        );

        self::assertSame('ms', app()->getLocale());
        self::assertSame(204, $response->getStatusCode());
    }

    public function test_it_uses_the_configured_locale_without_a_session_or_user(): void
    {
        config(['app.locale' => 'ms']);
        $request = Request::create('/admin');

        self::assertFalse($request->hasSession());

        $response = app(SetLocale::class)->handle(
            $request,
            fn (Request $request): Response => response()->noContent(),
        );

        self::assertSame('ms', app()->getLocale());
        self::assertSame(204, $response->getStatusCode());
    }
}
