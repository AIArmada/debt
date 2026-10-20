<?php

use Livewire\LivewireManager;

test('admin and customer login pages use their configured Livewire Alpine builds', function () {
    $isDebug = config('app.debug');
    $standardBuild = $isDebug ? 'livewire.js' : 'livewire.min.js';
    $cspBuild = $isDebug ? 'livewire.csp.js' : 'livewire.csp.min.js';

    $this->get('/admin/login')
        ->assertSee('vendor/livewire/'.$standardBuild, false)
        ->assertDontSee('vendor/livewire/'.$cspBuild, false);

    expect(config('livewire.csp_safe'))->toBeTrue();

    $livewire = app(LivewireManager::class);
    $livewire->flushState();
    $livewire->forceAssetInjection();

    try {
        $this->get('/login')
            ->assertSee('vendor/livewire/'.$cspBuild, false)
            ->assertDontSee('vendor/livewire/'.$standardBuild, false);

        expect(config('livewire.csp_safe'))->toBeTrue();
    } finally {
        $livewire->flushState();
    }
});
