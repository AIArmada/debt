<?php

use AIArmada\Authz\Models\Role;
use App\Actions\Promises\AcceptInvitation;
use App\Actions\Staff\CloseSupportCase;
use App\Actions\Staff\RevokeProfileInvite;
use App\Actions\Staff\RevokeSupportAccess;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\SubjectType;
use App\Domain\Enums\SupportAccessGrantStatus;
use App\Filament\Pages\SupportCaseWorkspace;
use App\Filament\Resources\FinancialProfiles\FinancialProfileResource;
use App\Filament\Resources\SupportCases\SupportCaseResource;
use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\PaymentDestination;
use App\Models\ProfileInvite;
use App\Models\QuantitySubject;
use App\Models\Record;
use App\Models\SupportAuditEntry;
use App\Models\SupportCase;
use App\Models\User;
use Database\Seeders\StaffRolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

function workspaceStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($role, 'web'));
    $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $user;
}

beforeEach(function (): void {
    $this->seed(StaffRolesAndPermissionsSeeder::class);
});

test('the workspace hides customer data until its case has an active grant', function () {
    $staff = workspaceStaff('support_agent');
    $profile = FinancialProfile::factory()->create(['name' => 'Sensitive profile name']);
    $case = SupportCase::factory()->create([
        'profile_id' => $profile->getKey(),
        'opened_by' => $staff->getKey(),
        'subject' => 'Metadata case subject',
        'reason' => 'Metadata case reason.',
    ]);
    Record::factory()->create(['profile_id' => $profile->getKey(), 'title' => 'Secret record title']);
    $party = Party::factory()->create(['profile_id' => $profile->getKey(), 'display_name' => 'Masked destination party']);
    PartyContact::factory()->create([
        'party_id' => $party->getKey(),
        'label' => 'email',
        'value' => 'profile-contact@example.test',
        'created_by' => $staff->getKey(),
    ]);
    PaymentDestination::factory()->create([
        'party_id' => $party->getKey(),
        'details_encrypted' => '9876543210',
        'created_by' => $staff->getKey(),
    ]);

    $this->actingAs($staff, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertSee('Metadata case subject')
        ->assertSee('Customer profile data is hidden')
        ->assertDontSee('Secret record title');

    $requester = workspaceStaff('support_agent');
    $approver = workspaceStaff('support_lead');
    $grant = $case->grants()->create([
        'requested_by' => $requester->getKey(),
        'approved_by' => $approver->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertSee('Secret record title')
        ->assertSee('Masked destination party')
        ->assertSee('profile-contact@example.test')
        ->assertSee('•••• 3210')
        ->assertDontSee('9876543210')
        ->assertDontSee('token_hash')
        ->assertDontSee('details_encrypted')
        ->assertDontSee('two_factor_secret');

    expect(SupportAuditEntry::query()->where('action', 'profile_data.viewed')->exists())->toBeTrue();

    app(RevokeSupportAccess::class)->handle($approver, $grant);
    $this->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertSee('Customer profile data is hidden')
        ->assertDontSee('Secret record title');
});

test('customer records have no global search or direct admin resource route and dashboard totals stay count-only', function () {
    $staff = workspaceStaff('support_agent');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create(['profile_id' => $profile->getKey(), 'opened_by' => $staff->getKey()]);
    Record::factory()->create(['profile_id' => $profile->getKey(), 'title' => 'Global search must not find this record']);

    $this->actingAs($staff, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $staff->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get('/admin')->assertOk()
        ->assertDontSee('Global search must not find this record')
        ->assertDontSee('Total balance');

    $this->get('/admin/records')->assertNotFound();

    expect(SupportCaseResource::getGloballySearchableAttributes())->toBe(['subject'])
        ->and(FinancialProfileResource::getGloballySearchableAttributes())->toBe(['name', 'owner.name', 'owner.email']);
});

test('attachment metadata and quantity subject details are visible only under an active grant', function () {
    $agent = workspaceStaff('support_agent');
    $lead = workspaceStaff('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create([
        'profile_id' => $profile->getKey(),
        'opened_by' => $agent->getKey(),
    ]);
    $record = Record::factory()->create([
        'profile_id' => $profile->getKey(),
        'title' => 'Quantity return record',
    ]);
    $obligation = Obligation::factory()->create([
        'record_id' => $record->getKey(),
        'subject_type' => SubjectType::Quantity,
        'title' => 'Return flour',
    ]);
    QuantitySubject::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'name' => 'Kitchen flour bags',
        'total' => '3.5000',
        'unit' => 'bag',
        'is_fractionable' => true,
    ]);
    $createdAt = now()->subDay()->setTime(9, 30);
    Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_type' => $record->getMorphClass(),
        'attachable_id' => $record->getKey(),
        'recorded_by' => $agent->getKey(),
        'original_name' => 'return-receipt.pdf',
        'category' => AttachmentCategory::Receipt,
        'mime' => 'application/pdf',
        'size_bytes' => 2048,
        'created_at' => $createdAt,
        'disk_path' => 'private/internal/receipt.pdf',
        'link_url' => null,
    ]);
    Attachment::factory()->create([
        'profile_id' => $profile->getKey(),
        'attachable_type' => $record->getMorphClass(),
        'attachable_id' => $record->getKey(),
        'recorded_by' => $agent->getKey(),
        'original_name' => 'linked-return-receipt.pdf',
        'category' => AttachmentCategory::Receipt,
        'mime' => 'application/pdf',
        'size_bytes' => 2048,
        'created_at' => $createdAt,
        'disk_path' => null,
        'link_url' => 'https://secret-download.example.test/receipt',
    ]);

    $this->actingAs($agent, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $agent->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertDontSee('return-receipt.pdf')
        ->assertDontSee('linked-return-receipt.pdf')
        ->assertDontSee('receipt')
        ->assertDontSee('application/pdf')
        ->assertDontSee('2048 bytes')
        ->assertDontSee($createdAt->toDateTimeString())
        ->assertDontSee('Kitchen flour bags');

    $case->grants()->create([
        'requested_by' => $agent->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertSee('return-receipt.pdf')
        ->assertSee('linked-return-receipt.pdf')
        ->assertSee('receipt')
        ->assertSee('application/pdf')
        ->assertSee('2048 bytes')
        ->assertSee($createdAt->toDateTimeString())
        ->assertSee('Kitchen flour bags')
        ->assertSee('Unit: bag')
        ->assertSee('Total quantity: 3.5000')
        ->assertSee('Fractionable: Yes')
        ->assertDontSee('private/internal/receipt.pdf')
        ->assertDontSee('https://secret-download.example.test/receipt');
});

test('workspace rechecks its grant after loading customer data', function () {
    $agent = workspaceStaff('support_agent');
    $lead = workspaceStaff('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create([
        'profile_id' => $profile->getKey(),
        'opened_by' => $agent->getKey(),
    ]);
    Record::factory()->create(['profile_id' => $profile->getKey(), 'title' => 'Revocation race record']);
    $grant = $case->grants()->create([
        'requested_by' => $agent->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $revokedDuringRender = false;

    DB::listen(function (QueryExecuted $query) use ($grant, &$revokedDuringRender): void {
        if ($revokedDuringRender || ! str_contains($query->sql, 'from "attachments"')) {
            return;
        }

        $revokedDuringRender = true;
        DB::table('support_access_grants')->where('id', $grant->getKey())->update([
            'status' => SupportAccessGrantStatus::Revoked->value,
            'revoked_at' => now(),
        ]);
    });

    $this->actingAs($agent, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $agent->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertForbidden();

    expect($revokedDuringRender)->toBeTrue();
});

test('workspace profile data audit is written once across Livewire re-renders per grant', function () {
    $agent = workspaceStaff('support_agent');
    $lead = workspaceStaff('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create([
        'profile_id' => $profile->getKey(),
        'opened_by' => $agent->getKey(),
    ]);
    $case->grants()->create([
        'requested_by' => $agent->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $panel = Filament::getPanel('admin');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The admin panel is not registered.');
    }

    Filament::setCurrentPanel($panel);

    $this->actingAs($agent, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $agent->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ]);

    Livewire::test(SupportCaseWorkspace::class, ['record' => (string) $case->getKey()])
        ->call('$refresh');

    expect(SupportAuditEntry::query()
        ->where('case_id', $case->getKey())
        ->where('action', 'profile_data.viewed')
        ->count())->toBe(1);
});

test('customer memberships do not grant access to a guessed workspace URL', function () {
    $customer = User::factory()->create();
    $profile = $customer->financialProfiles()->firstOrFail();
    $case = SupportCase::factory()->create(['profile_id' => $profile->getKey(), 'opened_by' => $customer->getKey()]);

    $this->actingAs($customer, 'web')->get("/admin/support-cases/{$case->getKey()}/workspace")->assertForbidden();
});

test('closing a case prevents access even while its grant has time remaining', function () {
    $agent = workspaceStaff('support_agent');
    $lead = workspaceStaff('support_lead');
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create(['profile_id' => $profile->getKey(), 'opened_by' => $agent->getKey()]);
    Record::factory()->create(['profile_id' => $profile->getKey(), 'title' => 'Closed case secret record']);
    $case->grants()->create([
        'requested_by' => $agent->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    app(CloseSupportCase::class)->handle($lead, $case);

    $this->actingAs($agent, 'web')->withSession([
        'staff_mfa_verified_user_id' => (string) $agent->getKey(),
        'staff_mfa_verified_at' => now()->timestamp,
    ])->get("/admin/support-cases/{$case->getKey()}/workspace")
        ->assertOk()
        ->assertSee('Customer profile data is hidden')
        ->assertDontSee('Closed case secret record');
});

test('staff can revoke an invitation under an active grant and it cannot later be accepted', function () {
    $agent = workspaceStaff('support_agent');
    $lead = workspaceStaff('support_lead');
    $invitee = User::factory()->create();
    $profile = FinancialProfile::factory()->create();
    $case = SupportCase::factory()->create(['profile_id' => $profile->getKey(), 'opened_by' => $agent->getKey()]);
    $case->grants()->create([
        'requested_by' => $agent->getKey(),
        'approved_by' => $lead->getKey(),
        'status' => SupportAccessGrantStatus::Approved,
        'starts_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $rawToken = 'invite-token-used-only-for-this-test';
    $invite = ProfileInvite::factory()->create([
        'profile_id' => $profile->getKey(),
        'email' => $invitee->email,
        'token_hash' => hash('sha256', $rawToken),
        'invited_by' => $profile->owner_user_id,
        'expires_at' => now()->addDay(),
    ]);

    $revoked = app(RevokeProfileInvite::class)->handle($lead, $invite, $case);

    expect($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->isUsable())->toBeFalse()
        ->and(SupportAuditEntry::query()->where('action', 'profile_invite.revoked')->exists())->toBeTrue();

    expect(fn () => app(AcceptInvitation::class)->handle($invitee, $rawToken))
        ->toThrow(ValidationException::class);
});
