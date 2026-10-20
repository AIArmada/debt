<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('guest root shows the product front page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('One clear record, shared by both sides.')
        ->assertSee('Start on your own')
        ->assertSee('only when you want')
        ->assertSee('New records are shared with profile members by default; private records are visible only to their creator and the profile owner.')
        ->assertDontSee('welcome.');
});

test('guest root renders Malay copy after changing the locale', function () {
    $this->from(route('home'))
        ->get(route('locale.switch', ['locale' => 'ms']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Satu rekod jelas untuk kedua-dua pihak.')
        ->assertSee('Mulakan sendiri')
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
