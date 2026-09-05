<?php

test('application responses include baseline security headers', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('X-Frame-Options', 'DENY');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

test('local livewire supports the runtime while production remains strict', function () {
    $localResponse = $this->get(route('home'));

    expect(config('livewire.csp_safe'))->toBeTrue()
        ->and($localResponse->headers->get('Content-Security-Policy'))->toContain("'unsafe-eval'");

    $this->app->detectEnvironment(fn () => 'production');
    $productionResponse = $this->get(route('home'));

    expect($productionResponse->headers->get('Content-Security-Policy'))->not->toContain("'unsafe-eval'");
});
