<?php

use AIArmada\Authz\Guard\SessionGuard as AuthzSessionGuard;
use AIArmada\Authz\Models\Role;
use App\Actions\Staff\AssignStaffRole;
use App\Actions\Staff\RevokeStaffRole;
use App\Models\SupportAuditEntry;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Database\Seeders\StaffRolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

function adminSecurityUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($role, 'web'));
    $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $user;
}

beforeEach(function (): void {
    $this->seed(StaffRolesAndPermissionsSeeder::class);
});

test('only security admin can assign or revoke seeded staff roles', function () {
    $lead = adminSecurityUser('support_lead');
    $securityAdmin = adminSecurityUser('security_admin');
    $target = User::factory()->create();

    expect(fn () => app(AssignStaffRole::class)->handle($lead, $target, 'support_agent'))
        ->toThrow(AuthorizationException::class);

    app(AssignStaffRole::class)->handle($securityAdmin, $target, 'support_agent');
    expect($target->fresh()->hasRole('support_agent', 'web'))->toBeTrue();

    expect(fn () => app(AssignStaffRole::class)->handle($securityAdmin, $target, 'super_admin'))
        ->toThrow(ValidationException::class);

    app(RevokeStaffRole::class)->handle($securityAdmin, $target, 'support_agent');
    expect($target->fresh()->hasAnyRole(User::STAFF_ROLES, 'web'))->toBeFalse();
});

test('the staff seeder creates only the settled permission map for web guard roles', function () {
    $expectedPermissions = [
        'support_agent' => [
            'profile.lookup', 'profile.viewSummary', 'supportCase.viewAny', 'supportCase.view',
            'supportCase.create', 'supportCase.update', 'supportAccess.request',
            'profileRecord.view', 'profileMember.view', 'profileInvite.revoke',
        ],
        'support_lead' => [
            'profile.lookup', 'profile.viewSummary', 'supportCase.viewAny', 'supportCase.view',
            'supportCase.create', 'supportCase.update', 'supportAccess.request',
            'profileRecord.view', 'profileMember.view', 'profileInvite.revoke',
            'supportAccess.approve', 'supportAccess.revoke', 'supportAudit.viewAny',
        ],
        'support_auditor' => ['supportAudit.viewAny'],
        'security_admin' => ['staffAccess.viewAny', 'staffAccess.assign'],
    ];

    foreach ($expectedPermissions as $roleName => $permissionNames) {
        $role = Role::findByName($roleName, 'web');

        expect($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($permissionNames)->sort()->values()->all());
    }
});

test('authz impersonation endpoints and role resources are not exposed', function () {
    $impersonationPaths = [
        '/authz/leave-impersonation',
        '/filament-authz/impersonate/123',
        '/filament-authz/impersonate/leave',
    ];

    foreach ($impersonationPaths as $path) {
        $this->post($path)->assertNotFound();
    }

    $this->get('/admin/authz/roles')->assertNotFound();
    $this->get('/admin/authz/permissions')->assertNotFound();

    $staff = adminSecurityUser('support_agent');

    foreach ($impersonationPaths as $path) {
        $this->actingAs($staff, 'web')->post($path)->assertNotFound();
    }
});

test('the Authz session guard signs remember cookies with the application key', function () {
    $guard = Auth::guard('web');

    expect($guard)->toBeInstanceOf(AuthzSessionGuard::class);

    if (! $guard instanceof AuthzSessionGuard) {
        throw new RuntimeException('The web guard is not the Authz session guard.');
    }

    expect($guard->hashPasswordForCookie('password-hash-for-test'))
        ->toBe(hash_hmac('sha256', 'password-hash-for-test', (string) config('app.key')));
});

test('support audit entries can only be created through the logger', function () {
    $lead = adminSecurityUser('support_lead');

    expect(fn () => SupportAuditEntry::create([
        'actor_id' => $lead->getKey(),
        'action' => 'direct.create.attempt',
        'metadata' => [],
        'created_at' => now(),
    ]))->toThrow(LogicException::class);

    $entry = app(SupportAuditLogger::class)->record($lead, 'test.logger.created');

    expect($entry->exists)->toBeTrue()
        ->and($entry->action)->toBe('test.logger.created');
});

test('support audit logger rejects non scalar metadata values', function () {
    $lead = adminSecurityUser('support_lead');
    $logger = app(SupportAuditLogger::class);

    foreach ([
        ['resource' => ['nested' => 'private']],
        ['count' => new stdClass],
        ['count' => 1.5],
    ] as $metadata) {
        expect(fn () => $logger->record($lead, 'test.invalid.metadata', metadata: $metadata))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('filament authz config contains only settings used without its plugin', function () {
    expect(require config_path('filament-authz.php'))
        ->toBe(['impersonate' => ['enabled' => false]]);
});

test('audit entries cannot be updated or deleted and have no write routes', function () {
    $lead = adminSecurityUser('support_lead');
    $entry = app(SupportAuditLogger::class)->record($lead, 'test.immutable');

    expect(fn () => $entry->forceFill(['action' => 'tampered'])->save())
        ->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);

    $this->actingAs($lead, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $lead->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get("/admin/support-audit-entries/{$entry->getKey()}/edit")->assertNotFound();

    $this->put("/admin/support-audit-entries/{$entry->getKey()}")->assertNotFound();
    $this->delete("/admin/support-audit-entries/{$entry->getKey()}")->assertNotFound();
});

test('database trigger rejects bulk audit updates', function () {
    $lead = adminSecurityUser('support_lead');
    $entry = app(SupportAuditLogger::class)->record($lead, 'test.bulk.update');

    expect(fn () => SupportAuditEntry::query()->whereKey($entry->getKey())->update(['action' => 'bulk tampered']))
        ->toThrow(QueryException::class);
});

test('database trigger rejects bulk audit deletes', function () {
    $lead = adminSecurityUser('support_lead');
    $entry = app(SupportAuditLogger::class)->record($lead, 'test.bulk.delete');

    expect(fn () => SupportAuditEntry::query()->whereKey($entry->getKey())->delete())
        ->toThrow(QueryException::class);
});

test('staff pages render no password, two factor, token, payment, or terms secrets', function () {
    $admin = adminSecurityUser('security_admin');
    $admin->forceFill([
        'password' => 'password-marker',
        'two_factor_secret' => 'fortify-secret-marker',
        'two_factor_recovery_codes' => 'fortify-recovery-marker',
    ])->save();
    $admin->makeVisible(['password', 'two_factor_secret', 'two_factor_recovery_codes']);

    $this->actingAs($admin, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $admin->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get('/admin/staff-users')->assertOk()
        ->assertDontSee('password-marker')
        ->assertDontSee('fortify-secret-marker')
        ->assertDontSee('fortify-recovery-marker')
        ->assertDontSee('password')
        ->assertDontSee('two_factor')
        ->assertDontSee('token_hash')
        ->assertDontSee('details_encrypted')
        ->assertDontSee('terms_hash');
});
