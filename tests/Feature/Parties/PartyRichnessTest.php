<?php

use App\Actions\Parties\AddPartyContact;
use App\Actions\Parties\AddPartyRelationship;
use App\Actions\Parties\AddPaymentDestination;
use App\Actions\Parties\Data\PartyContactData;
use App\Actions\Parties\Data\PartyRelationshipData;
use App\Actions\Parties\Data\PaymentDestinationData;
use App\Actions\Parties\RemovePartyContact;
use App\Actions\Parties\RemovePartyRelationship;
use App\Actions\Parties\RemovePaymentDestination;
use App\Actions\Parties\RevealPaymentDestination;
use App\Actions\Parties\SetPrimaryContact;
use App\Actions\Parties\VerifyPaymentDestination;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\RecordMoneyMovement;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRelationshipKind;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\PaymentDestinationKind;
use App\Models\PartyContact;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

test('contacts support add remove primary changes and database uniqueness', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Ali',
        'status' => PartyStatus::Active,
    ]);

    $first = app(AddPartyContact::class)->handle($owner, $party, PartyContactData::fromInput([
        'label' => 'mobile',
        'value' => '+60123456789',
        'isPrimary' => true,
    ]));
    $second = app(AddPartyContact::class)->handle($owner, $party, PartyContactData::fromInput([
        'label' => 'WhatsApp',
        'value' => '@ali',
        'isPrimary' => false,
    ]));
    app(SetPrimaryContact::class)->handle($owner, $second);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();

    app(RemovePartyContact::class)->handle($owner, $first);
    $this->assertDatabaseMissing('party_contacts', ['id' => $first->getKey()]);
    app(RemovePartyContact::class)->handle($owner, $second);
});

test('party contact primary uniqueness is enforced by the database index', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Ali',
        'status' => PartyStatus::Active,
    ]);

    PartyContact::factory()->for($party)->create(['is_primary' => true]);

    expect(fn () => PartyContact::factory()->for($party)->create(['is_primary' => true]))
        ->toThrow(QueryException::class);
});

test('contact writes are viewer-blocked and party routes stay profile scoped', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $otherOwner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $otherProfile = $otherOwner->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Ali',
        'status' => PartyStatus::Active,
    ]);
    $otherParty = $otherProfile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Other Ali',
        'status' => PartyStatus::Active,
    ]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);

    expect(fn () => app(AddPartyContact::class)->handle($viewer, $party, PartyContactData::fromInput([
        'label' => 'email',
        'value' => 'ali@example.test',
    ])))->toThrow(AuthorizationException::class);

    $this->actingAs($owner)
        ->get(route('people.show', [$otherProfile, $otherParty]))
        ->assertNotFound();
});

test('payment destinations are encrypted masked by default audited on reveal and independent of movements', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Ahmad',
        'status' => PartyStatus::Active,
    ]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);
    $destination = app(AddPaymentDestination::class)->handle($owner, $party, PaymentDestinationData::fromInput([
        'kind' => PaymentDestinationKind::BankAccount->value,
        'label' => 'Main account',
        'details' => '1234567894567',
    ]));
    $ciphertext = (string) DB::table('payment_destinations')->where('id', $destination->getKey())->value('details_encrypted');

    expect($ciphertext)->not->toContain('1234567894567')
        ->and($destination->fresh()->details_encrypted)->toBe('1234567894567');
    $this->actingAs($owner)->get(route('people.show', [$profile, $party]))
        ->assertOk()->assertSee('•••• 4567')->assertDontSee('1234567894567')->assertSee('Reveal once');

    expect(app(RevealPaymentDestination::class)->handle($owner, $destination))->toBe('1234567894567');
    $this->assertDatabaseHas('activity_entries', [
        'subject_id' => $destination->getKey(),
        'action' => 'payment_destination_revealed',
        'actor_user_id' => $owner->getKey(),
    ]);

    $verified = app(VerifyPaymentDestination::class)->handle($owner, $destination);
    expect($verified->is_verified)->toBeTrue()
        ->and($verified->verified_by)->toBe($owner->getKey())
        ->and($verified->verified_at)->not->toBeNull();

    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => $party->display_name,
        'partyId' => $party->getKey(),
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));
    $before = $destination->fresh()->getAttributes();
    app(RecordMoneyMovement::class)->handle($owner, $record->obligations->firstOrFail(), RecordMovementData::settlement(1000, 'MYR', today()->toDateString()));
    expect($destination->fresh()->getAttributes())->toMatchArray([
        'id' => $before['id'],
        'label' => $before['label'],
        'is_verified' => $before['is_verified'],
        'verified_by' => $before['verified_by'],
    ]);

    $removable = app(AddPaymentDestination::class)->handle($owner, $party, PaymentDestinationData::fromInput([
        'kind' => PaymentDestinationKind::Other->value,
        'label' => 'Temporary reference',
        'details' => 'Remove me',
    ]));
    app(RemovePaymentDestination::class)->handle($owner, $removable);
    $this->assertDatabaseMissing('payment_destinations', ['id' => $removable->getKey()]);

    expect(fn () => app(RevealPaymentDestination::class)->handle($viewer, $destination))
        ->toThrow(AuthorizationException::class);
    $this->actingAs($viewer)
        ->get(route('people.show', [$profile, $party]))
        ->assertOk()
        ->assertSee('•••• 4567')
        ->assertDontSee('Reveal once')
        ->assertDontSee('1234567894567');
});

test('relationships stay directed, render from both sides, reject self and duplicates, and require one profile', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $otherOwner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $otherProfile = $otherOwner->financialProfiles()->firstOrFail();
    $ali = $profile->parties()->create(['kind' => PartyKind::Individual, 'display_name' => 'Ali', 'status' => PartyStatus::Active]);
    $ahmad = $profile->parties()->create(['kind' => PartyKind::Individual, 'display_name' => 'Ahmad', 'status' => PartyStatus::Active]);
    $otherParty = $otherProfile->parties()->create(['kind' => PartyKind::Individual, 'display_name' => 'Other', 'status' => PartyStatus::Active]);
    ProfileMember::query()->forceCreate([
        'profile_id' => $profile->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => MemberRole::Viewer,
        'accepted_at' => now(),
        'revoked_at' => null,
    ]);

    $data = PartyRelationshipData::fromInput([
        'toPartyId' => $ahmad->getKey(),
        'kind' => PartyRelationshipKind::AssistantOf->value,
    ]);
    $relationship = app(AddPartyRelationship::class)->handle($owner, $ali, $data);

    $this->actingAs($owner)->get(route('people.show', [$profile, $ali]))
        ->assertOk()->assertSee('Ali — assistant of → Ahmad')->assertSee('Assists');
    $this->actingAs($owner)->get(route('people.show', [$profile, $ahmad]))
        ->assertOk()->assertSee('Ali — assistant of → Ahmad')->assertSee('Assisted by');

    expect(fn () => app(AddPartyRelationship::class)->handle($owner, $ali, $data))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AddPartyRelationship::class)->handle($owner, $ali, PartyRelationshipData::fromInput([
        'toPartyId' => $ali->getKey(),
        'kind' => PartyRelationshipKind::AssistantOf->value,
    ])))->toThrow(ValidationException::class);
    expect(fn () => app(AddPartyRelationship::class)->handle($owner, $ali, PartyRelationshipData::fromInput([
        'toPartyId' => $otherParty->getKey(),
        'kind' => PartyRelationshipKind::AssistantOf->value,
    ])))->toThrow(ValidationException::class);
    expect(fn () => app(AddPartyRelationship::class)->handle($viewer, $ali, PartyRelationshipData::fromInput([
        'toPartyId' => $ahmad->getKey(),
        'kind' => PartyRelationshipKind::RelativeOf->value,
    ])))->toThrow(AuthorizationException::class);

    app(RemovePartyRelationship::class)->handle($owner, $relationship);
    $this->assertDatabaseMissing('party_relationships', ['id' => $relationship->getKey()]);
});
