<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('guest root shows the product front page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Clarity for what you owe')
        ->assertSee('Private by default');
});

test('authenticated user is sent to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
