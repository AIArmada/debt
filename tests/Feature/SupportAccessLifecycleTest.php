<?php

use AIArmada\Authz\Models\Role;
use App\Actions\Staff\ApproveSupportAccess;
use App\Actions\Staff\CloseSupportCase;
use App\Actions\Staff\OpenSupportCase;
use App\Actions\Staff\RequestSupportAccess;
use App\Actions\Staff\RevokeSupportAccess;
use App\Domain\Enums\SupportAccessGrantStatus;
use App\Domain\Enums\SupportCaseStatus;
use App\Models\FinancialProfile;
use App\Models\SupportAuditEntry;
use App\Models\SupportCase;
use App\Models\User;
use Database\Seeders\StaffRolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

function lifecycleStaffUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($role, 'web'));

    return $user;
}

beforeEach(function (): void {
    $this->seed(StaffRolesAndPermissionsSeeder::class);
});

test('case and grant lifecycle rejects self approval and limits approved access to one hour', function () {
    $requester = lifecycleStaffUser('support_agent');
    $approver = lifecycleStaffUser('support_lead');
    $profile = FinancialProfile::factory()->create();

    $case = app(OpenSupportCase::class)->handle($requester, $profile, 'Customer callback', 'Confirm profile ownership before investigating.');
    expect($case->status)->toBe(SupportCaseStatus::Open);

    $grant = app(RequestSupportAccess::class)->handle($requester, $case);
    expect($grant->status)->toBe(SupportAccessGrantStatus::Pending);

    expect(fn () => app(ApproveSupportAccess::class)->handle($requester, $grant))
        ->toThrow(ValidationException::class);

    $approved = app(ApproveSupportAccess::class)->handle($approver, $grant, 60);
    expect($approved->status)->toBe(SupportAccessGrantStatus::Approved)
        ->and($approved->isActive())->toBeTrue()
        ->and($approved->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(60);

    $this->travel(61)->minutes();
    expect($approved->fresh()->isActive())->toBeFalse();

    $this->artisan('support-access:expire')->assertExitCode(0);
    expect($approved->fresh()->status)->toBe(SupportAccessGrantStatus::Expired);
});

test('revoking a grant and closing its case each remove profile access', function () {
    $requester = lifecycleStaffUser('support_agent');
    $lead = lifecycleStaffUser('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = app(OpenSupportCase::class)->handle($requester, $profile, 'Access check', 'Review one profile.');
    $grant = app(RequestSupportAccess::class)->handle($requester, $case);
    $approved = app(ApproveSupportAccess::class)->handle($lead, $grant);

    $revoked = app(RevokeSupportAccess::class)->handle($lead, $approved);
    expect($revoked->status)->toBe(SupportAccessGrantStatus::Revoked)
        ->and($revoked->fresh()->isActive())->toBeFalse();

    $secondGrant = app(RequestSupportAccess::class)->handle($requester, $case);
    $approvedSecond = app(ApproveSupportAccess::class)->handle($lead, $secondGrant);
    $closedCase = app(CloseSupportCase::class)->handle($lead, $case);

    expect($closedCase->status)->toBe(SupportCaseStatus::Closed)
        ->and($approvedSecond->fresh()->isActive())->toBeFalse()
        ->and($closedCase->activeGrant())->toBeNull();

    expect(SupportAuditEntry::query()->whereIn('action', [
        'support_case.opened', 'support_access.requested', 'support_access.approved', 'support_access.revoked', 'support_case.closed',
    ])->count())->toBeGreaterThanOrEqual(7);
});

test('closed cases cannot receive new access requests', function () {
    $requester = lifecycleStaffUser('support_agent');
    $lead = lifecycleStaffUser('support_lead');
    $case = SupportCase::factory()->create(['opened_by' => $requester->getKey()]);
    app(CloseSupportCase::class)->handle($lead, $case);

    expect(fn () => app(RequestSupportAccess::class)->handle($requester, $case->fresh()))
        ->toThrow(AuthorizationException::class);
});
