<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('guest root shows the product front page', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
