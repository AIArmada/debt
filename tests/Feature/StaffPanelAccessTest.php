<?php

use AIArmada\Authz\Models\Role;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\SupportAccessGrantStatus;
use App\Http\Middleware\EnsureFreshStaffMfa;
use App\Models\FinancialProfile;
use App\Models\ProfileInvite;
use App\Models\ProfileMember;
use App\Models\SupportCase;
use App\Models\User;
use Database\Seeders\StaffRolesAndPermissionsSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Symfony\Component\HttpFoundation\Response;

pest()->use(RefreshDatabase::class);

function staffPanelUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($role, 'web'));

    return $user;
}

beforeEach(function (): void {
    $this->seed(StaffRolesAndPermissionsSeeder::class);
});

test('panel access requires verified email, a seeded staff role, and the admin panel id', function () {
    $panel = Filament::getPanel('admin');
    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    $unverified = User::factory()->unverified()->create();
    $unverified->assignRole(Role::findByName('support_agent', 'web'));
    expect($unverified->canAccessPanel($panel))->toBeFalse();

    $customer = User::factory()->create();
    $customerProfile = $customer->financialProfiles()->firstOrFail();
    expect($customer->canAccessPanel($panel))->toBeFalse();

    foreach ([MemberRole::Editor, MemberRole::Viewer] as $memberRole) {
        $member = User::factory()->create();
        ProfileMember::query()->forceCreate([
            'profile_id' => $customerProfile->getKey(),
            'user_id' => $member->getKey(),
            'role' => $memberRole,
            'accepted_at' => now(),
        ]);
        expect($member->canAccessPanel($panel))->toBeFalse();
    }

    foreach (User::STAFF_ROLES as $staffRole) {
        expect(staffPanelUser($staffRole)->canAccessPanel($panel))->toBeTrue();
    }

    expect(staffPanelUser('support_agent')->canAccessPanel(Panel::make()->id('other')))->toBeFalse();
});

test('guest requests reach the admin login and unverified customers cannot open the panel', function () {
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

    $customer = User::factory()->create();
    $this->actingAs($customer, 'web')->get('/admin')->assertForbidden();
});

test('factory-created staff with missing app authentication attributes are sent to MFA setup', function () {
    $staff = staffPanelUser('support_agent');

    expect(array_key_exists('app_authentication_secret', $staff->getAttributes()))->toBeFalse()
        ->and(array_key_exists('app_authentication_recovery_codes', $staff->getAttributes()))->toBeFalse();

    $this->actingAs($staff, 'web')
        ->get('/admin')
        ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
});

test('partial user selections safely return missing app authentication attributes', function () {
    $user = User::factory()->create();
    $partialUser = User::query()->select('id')->findOrFail($user->getKey());

    expect($partialUser->getAppAuthenticationSecret())->toBeNull()
        ->and($partialUser->getAppAuthenticationRecoveryCodes())->toBeNull();
});

test('staff sessions must pass a fresh native TOTP challenge and enroll before panel access', function () {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($staff, 'web')->get('/admin')->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));

    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    $this->actingAs($staff, 'web')->get('/admin')->assertRedirect(route('staff.mfa.challenge'));

    $this->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->actingAs($staff, 'web')->get('/admin')->assertOk();
});

test('the external freshness challenge accepts and consumes native recovery codes', function () {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $staff->saveAppAuthenticationRecoveryCodes([Hash::make('one-use-recovery-code')]);

    $this->actingAs($staff, 'web')->post(route('staff.mfa.verify'), [
        'recovery_code' => 'one-use-recovery-code',
    ])->assertRedirect('/admin');

    expect($staff->fresh()->getAppAuthenticationRecoveryCodes())->toBe([]);
});

test('stale MFA markers are cleared and sent back to the challenge on full requests', function () {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    $this->actingAs($staff, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->subSeconds(901)->timestamp,
    ])->get('/admin')
        ->assertRedirect(route('staff.mfa.challenge'))
        ->assertSessionMissing('staff_mfa_verified_user_id')
        ->assertSessionMissing('staff_mfa_verified_at');
});

test('staff TOTP uses a one-step code window', function () {
    $panel = Filament::getPanel('admin');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    $authentication = $panel->getMultiFactorAuthenticationProviders()['app'];

    expect($authentication)->toBeInstanceOf(AppAuthentication::class);

    if (! $authentication instanceof AppAuthentication) {
        throw new RuntimeException('The staff panel app authentication provider is not registered.');
    }

    expect($authentication->getCodeWindow())->toBe(1)
        ->and(app(AppAuthentication::class)->getCodeWindow())->toBe(1);
});

test('the admin panel registers staff MFA as persistent auth middleware', function () {
    $panel = Filament::getPanel('admin');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    expect($panel->getAuthMiddleware())
        ->toContain(EnsureFreshStaffMfa::class)
        ->and(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(EnsureFreshStaffMfa::class);
});

test('directly invoked staff MFA middleware passes a fresh marker', function () {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $panel = Filament::getPanel('admin');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    Filament::setCurrentPanel($panel);
    $this->actingAs($staff, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ]);

    $request = Request::create('/admin/support-cases/1/workspace');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (?string $guard = null): ?User => $guard === 'web' ? $staff : null);
    $nextCalled = false;
    $response = app(EnsureFreshStaffMfa::class)->handle(
        $request,
        function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('passed');
        },
    );

    expect($nextCalled)->toBeTrue()
        ->and($response->getContent())->toBe('passed');
});

test('directly invoked staff MFA middleware redirects cleared or stale markers and clears them', function (string $markerState) {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $panel = Filament::getPanel('admin');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    Filament::setCurrentPanel($panel);

    $sessionData = $markerState === 'stale' ? [
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->subSeconds(901)->timestamp,
    ] : [];
    $this->actingAs($staff, 'web')->withSession($sessionData);

    $request = Request::create('/admin/support-cases/1/workspace');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (?string $guard = null): ?User => $guard === 'web' ? $staff : null);
    $response = app(EnsureFreshStaffMfa::class)->handle(
        $request,
        fn (Request $request): Response => new Response('passed'),
    );

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe(route('staff.mfa.challenge'))
        ->and(app('session.store')->has('staff_mfa_verified_user_id'))->toBeFalse()
        ->and(app('session.store')->has('staff_mfa_verified_at'))->toBeFalse();
})->with([
    'cleared marker' => 'cleared',
    'stale marker' => 'stale',
]);

test('a stale MFA marker redirects from the workspace before an invite is revoked', function () {
    $staff = staffPanelUser('support_agent');
    $staff->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $lead = staffPanelUser('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create([
        'profile_id' => $profile->getKey(),
        'opened_by' => $staff->getKey(),
    ]);
    $case->grants()->create([
        'requested_by' => $staff->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $invite = ProfileInvite::factory()->create([
        'profile_id' => $profile->getKey(),
        'invited_by' => $profile->owner_user_id,
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($staff, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->subSeconds(901)->timestamp,
    ])->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertRedirect(route('staff.mfa.challenge'))
        ->assertSessionMissing('staff_mfa_verified_user_id')
        ->assertSessionMissing('staff_mfa_verified_at');

    expect($invite->fresh()->revoked_at)->toBeNull();
});
