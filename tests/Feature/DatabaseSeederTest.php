<?php

use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

pest()->use(RefreshDatabase::class);

test('database seeder provides a ready to use demo account', function () {
    $this->seed();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    expect($user->name)->toBe('Test User')
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and($profile->records()->count())->toBe(2)
        ->and($profile->records()->where('title', 'Home financing')->firstOrFail()->obligations()->where('direction', 'payable')->value('title'))->toBe('Home financing balance');
});
