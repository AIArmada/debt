<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('that true is true', function () {
    expect(true)->toBeTrue();
});
