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

test('authenticated user is sent to their promises home', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('promises.index', ['profile' => $profile]));
});
