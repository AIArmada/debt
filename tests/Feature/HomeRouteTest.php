<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('guest root shows the product front page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('ClearTogether')
        ->assertSee('One clear record, shared by both sides.')
        ->assertSee('Track money, promises or commitments in one place')
        ->assertSee('Share a link with the other person')
        ->assertSee('Promises are easy. Keeping track isn’t.')
        ->assertSee('Track any kind of promise')
        ->assertSee('Straight answers to common questions.')
        ->assertSee('Choose who can see each record.')
        ->assertDontSee('welcome.');
});

test('guest root renders Malay copy after changing the locale', function () {
    $this->from(route('home'))
        ->get(route('locale.switch', ['locale' => 'ms']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Satu rekod jelas untuk kedua-dua pihak.')
        ->assertSee('Jejaki wang, janji atau komitmen di satu tempat')
        ->assertSee('tanpa akaun')
        ->assertDontSee('welcome.');
});

test('authenticated user is sent to their promises home', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('promises.index', ['profile' => $profile]));
});
