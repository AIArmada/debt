<?php

use App\Features\PromiseTracker\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('guest root shows the product front page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<span>Promise</span>', false)
        ->assertSee('images/promise-logo.png', false)
        ->assertSee('images/debt-notebook.png', false)
        ->assertSee('images/personal-record.png', false)
        ->assertSee('Never forget what you owe')
        ->assertSee('including the secret ones')
        ->assertSee('Attach the proof')
        ->assertSee('The most important debts exist only in memory.')
        ->assertSee('Log secret debts')
        ->assertSee('Debts both sides accept')
        ->assertSee('Endings for real life')
        ->assertSee('What if they cannot pay?')
        ->assertSee('2 receipts attached')
        ->assertSee('If something happened to you tomorrow')
        ->assertSee('Export anytime')
        ->assertSee('Straight answers to common questions.')
        ->assertSee('Only you can see your records.')
        ->assertDontSee('welcome.');
});

test('guest root renders Malay copy after changing the locale', function () {
    $this->from(route('home'))
        ->get(route('locale.switch', ['locale' => 'ms']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Jangan lupa apa yang anda hutang')
        ->assertSee('termasuk yang rahsia')
        ->assertSee('tidak memerlukan akaun')
        ->assertSee('Jika sesuatu terjadi kepada anda esok')
        ->assertSee('Eksport bila-bila')
        ->assertDontSee('welcome.');
});

test('authenticated user is sent to their promises home', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('promises.index', ['profile' => $profile]));
});
