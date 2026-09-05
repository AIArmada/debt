<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registration rejects passwords shorter than eight characters', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Short Password User',
        'email' => 'short-password@example.com',
        'password' => 'short7',
        'password_confirmation' => 'short7',
    ]);

    $response->assertSessionHasErrors('password');
});
