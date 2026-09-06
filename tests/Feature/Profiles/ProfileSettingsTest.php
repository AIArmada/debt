<?php

use App\Actions\Promises\CreateApiToken;
use App\Actions\Promises\Data\CreateApiTokenData;
use App\Actions\Promises\Data\UpdateFinancialProfileData;
use App\Actions\Promises\RevokeApiToken;
use App\Actions\Promises\UpdateFinancialProfile;
use App\Domain\Enums\ApiTokenAbility;
use App\Domain\Enums\MemberRole;
use App\Livewire\Profiles\Settings as ProfileSettings;
use App\Livewire\Profiles\Tokens as ProfileTokens;
use App\Models\ApiToken;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('profile settings update only changes the name and timezone', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $originalCurrency = $profile->base_currency;

    app(UpdateFinancialProfile::class)->handle($user, $profile, UpdateFinancialProfileData::fromInput([
        'name' => 'Household',
        'timezone' => 'UTC',
    ]));

    expect($profile->fresh()->name)->toBe('Household')
        ->and($profile->fresh()->timezone)->toBe('UTC')
        ->and($profile->fresh()->base_currency)->toBe($originalCurrency);
});

test('viewers cannot open profile settings', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);

    $this->actingAs($viewer)
        ->get(route('profile-settings.edit', $profile))
        ->assertForbidden();
});

test('the profile switcher lists accessible profiles and changes only the route profile', function () {
    $user = User::factory()->create();
    $firstProfile = $user->financialProfiles()->firstOrFail();
    $secondProfile = $user->financialProfiles()->create([
        'name' => 'Travel',
        'base_currency' => 'USD',
        'timezone' => 'UTC',
        'is_archived' => false,
    ]);

    $response = $this->actingAs($user)->get(route('promises.index', $firstProfile));

    $response->assertOk()
        ->assertSee($firstProfile->name)
        ->assertSee($secondProfile->name)
        ->assertSee(route('promises.index', ['profile' => $secondProfile]));
});

test('profile settings save through the owner-only livewire component', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    Livewire::test(ProfileSettings::class, ['profile' => $profile])
        ->set('name', 'Renamed profile')
        ->set('timezone', 'Europe/London')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('financial_profiles', [
        'id' => $profile->getKey(),
        'name' => 'Renamed profile',
        'timezone' => 'Europe/London',
        'base_currency' => 'MYR',
    ]);
});

test('token management shows a secret once and revoking it invalidates the token', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    $component = Livewire::test(ProfileTokens::class, ['profile' => $profile])
        ->set('name', 'Read integration')
        ->set('abilities', [ApiTokenAbility::Read->value])
        ->call('createToken')
        ->assertHasNoErrors();
    $plainToken = $component->get('latestPlainToken');
    $token = ApiToken::query()->where('profile_id', $profile->getKey())->firstOrFail();

    expect($plainToken)->toBeString()
        ->and($token->abilities)->toBe([ApiTokenAbility::Read->value]);

    $this->get(route('profile-tokens.index', $profile))->assertOk()->assertDontSee($plainToken);
    $component->call('revoke', $token->getKey())->assertHasNoErrors();
    $this->assertDatabaseMissing('api_tokens', ['id' => $token->getKey()]);
    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/v1/promises')
        ->assertUnauthorized();
});

test('tokens cannot be revoked through another profile', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $otherProfile = $otherUser->financialProfiles()->firstOrFail();
    $token = app(CreateApiToken::class)->handle(
        $user,
        $profile,
        CreateApiTokenData::fromInput(['name' => 'Private']),
    );

    expect(fn () => app(RevokeApiToken::class)->handle($user, $otherProfile, $token))
        ->toThrow(AuthorizationException::class);
});
