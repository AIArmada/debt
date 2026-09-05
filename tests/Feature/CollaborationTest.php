<?php

use App\Livewire\Profiles\Edit;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ProfileInvitation;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('owner can create an expiring hashed invitation', function () {
    $owner = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $owner->id)->firstOrFail();

    $component = Livewire::actingAs($owner)
        ->test(Edit::class, ['profile' => $profile])
        ->set('inviteEmail', 'member@example.test')
        ->set('inviteRole', 'viewer')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = ProfileInvitation::query()->firstOrFail();
    $invitationUrl = (string) $component->get('invitationUrl');
    $token = (string) last(explode('/', $invitationUrl));
    expect($invitation->token_hash)->not->toBe($token)
        ->and($invitation->role)->toBe('viewer')
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

test('matching user can accept invitation and read but not edit', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'member@example.test']);
    $profile = FinancialProfile::query()->where('owner_user_id', $owner->id)->firstOrFail();
    $obligation = createCollaborationObligation($profile, ['sensitivity' => 'shared']);
    $token = 'valid-invitation-token';
    $invitation = ProfileInvitation::create([
        'profile_id' => $profile->id,
        'invited_by_user_id' => $owner->id,
        'email' => $member->email,
        'role' => 'viewer',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($member)->get(route('invitations.accept', $token))
        ->assertRedirect(route('financial-profiles.index'));
    $this->assertDatabaseHas('profile_members', [
        'profile_id' => $profile->id,
        'user_id' => $member->id,
        'role' => 'viewer',
    ]);

    $this->actingAs($member)->get(route('records.show', $obligation->record))
        ->assertOk()
        ->assertSee($obligation->title)
        ->assertDontSee('Add movement')
        ->assertDontSee('Edit');

    $this->actingAs($member)->get(route('records.obligations.edit', [$obligation->record, $obligation]))
        ->assertForbidden();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('invitation cannot be accepted by a different email', function () {
    $owner = User::factory()->create();
    $wrongUser = User::factory()->create(['email' => 'wrong@example.test']);
    $profile = FinancialProfile::query()->where('owner_user_id', $owner->id)->firstOrFail();
    $token = 'email-bound-token';
    ProfileInvitation::create([
        'profile_id' => $profile->id,
        'invited_by_user_id' => $owner->id,
        'email' => 'member@example.test',
        'role' => 'editor',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($wrongUser)->get(route('invitations.accept', $token))
        ->assertForbidden();
    expect(ProfileMember::query()->count())->toBe(0);
});

function createCollaborationObligation(FinancialProfile $profile, array $record = []): Obligation
{
    return $profile->records()->create(array_merge(['title' => 'Shared arrangement'], $record))->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Shared record',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);
}
