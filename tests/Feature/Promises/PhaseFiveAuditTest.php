<?php

use App\Actions\Promises\CreateApiToken;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreateApiTokenData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\SetExchangeRateData;
use App\Actions\Promises\Data\UpdatePromiseDetailsData;
use App\Actions\Promises\DeleteRecord;
use App\Actions\Promises\SetExchangeRate;
use App\Actions\Promises\UpdatePromiseDetails;
use App\Domain\Enums\ApiTokenAbility;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\ConvertedView;
use App\Models\Attachment;
use App\Models\MoneyMovement;
use App\Models\ProfileMember;
use App\Models\QuantityReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

test('read-only API tokens cannot write', function () {
    $owner = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $token = app(CreateApiToken::class)->handle($owner, $profile, CreateApiTokenData::fromInput([
        'name' => 'Read-only CLI',
        'abilities' => [ApiTokenAbility::Read->value],
    ]));
    $plainToken = (string) $token->getAttribute('plain_token');

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/v1/promises')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->postJson('/api/v1/promises', [
            'partyName' => 'Blocked write',
            'direction' => Direction::Payable->value,
            'subjectType' => SubjectType::Money->value,
            'amount' => '1.00',
        ])
        ->assertForbidden();
});

test('FX display conversion uses decimal arithmetic', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Exact FX',
        'direction' => Direction::Payable->value,
        'amount' => '1.00',
    ], 'MYR'));
    app(SetExchangeRate::class)->handle($user, $profile, SetExchangeRateData::fromInput([
        'from' => 'MYR',
        'to' => 'USD',
        'rate' => '1.005',
        'ratedOn' => today()->toDateString(),
        'source' => 'test',
    ]));

    expect(app(ConvertedView::class)->forProfile($profile, 'USD')['to_pay']['USD']['amount_minor'])
        ->toBe(101);
});

test('the home route resolves an accessible member profile', function () {
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
        ->get(route('home'))
        ->assertRedirect(route('promises.index', ['profile' => $profile]));
});

test('promise details can change title and a past due date without changing ledger fields', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Original promise', 'note' => null, 'is_archived' => false]);
    $obligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Commitment,
        'due_on' => today()->addDay(),
    ]);
    $obligation->commitmentSubject()->create(['done_criteria' => 'Send the files.']);

    app(UpdatePromiseDetails::class)->handle($user, $record, UpdatePromiseDetailsData::fromInput([
        'record_id' => $record->getKey(),
        'obligation_id' => $obligation->getKey(),
        'title' => 'Renegotiated promise',
        'due_on' => today()->subDay()->toDateString(),
    ]));

    expect($record->fresh()->title)->toBe('Renegotiated promise')
        ->and($obligation->fresh()->title)->toBe('Renegotiated promise')
        ->and($obligation->fresh()->due_on->toDateString())->toBe(today()->subDay()->toDateString());
    $this->assertDatabaseHas('activity_entries', [
        'profile_id' => $profile->getKey(),
        'subject_id' => $record->getKey(),
        'action' => 'promise_details_updated',
    ]);
});

test('promise details cannot be changed after a confirmed movement', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Locked details',
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
    ], 'MYR'));

    expect(fn () => app(UpdatePromiseDetails::class)->handle($user, $record, UpdatePromiseDetailsData::fromInput([
        'recordId' => $record->getKey(),
        'title' => 'Should fail',
        'dueOn' => null,
    ])))->toThrow(ValidationException::class);
});

test('a record can be deleted only when it has no confirmed movements, returns, or attachments', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Delete me', 'note' => null, 'is_archived' => false]);
    $obligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Commitment,
    ]);
    $obligation->commitmentSubject()->create(['done_criteria' => 'Complete it.']);

    expect($record->canBeDeleted())->toBeTrue();
    app(DeleteRecord::class)->handle($user, $record);

    $this->assertDatabaseMissing('records', ['id' => $record->getKey()]);
    $this->assertDatabaseHas('activity_entries', [
        'profile_id' => $profile->getKey(),
        'subject_id' => $record->getKey(),
        'action' => 'record_deleted',
    ]);
});

test('record deletion is blocked by each protected child type', function (string $protectedBy) {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Protected record', 'note' => null, 'is_archived' => false]);

    if ($protectedBy === 'movement') {
        $obligation = $record->obligations()->create([
            'direction' => Direction::Payable,
            'title' => $record->title,
            'status' => ObligationStatus::Open,
            'subject_type' => SubjectType::Money,
        ]);
        $obligation->moneySubject()->create(['currency' => 'MYR']);
        MoneyMovement::factory()->create([
            'obligation_id' => $obligation->getKey(),
            'recorded_by' => $user->getKey(),
            'status' => MovementStatus::Confirmed,
        ]);
    } elseif ($protectedBy === 'return') {
        $obligation = $record->obligations()->create([
            'direction' => Direction::Payable,
            'title' => $record->title,
            'status' => ObligationStatus::Open,
            'subject_type' => SubjectType::Quantity,
        ]);
        $obligation->quantitySubject()->create([
            'name' => 'Camera',
            'total' => '1.0000',
            'unit' => 'item',
            'is_fractionable' => false,
        ]);
        QuantityReturn::factory()->create([
            'obligation_id' => $obligation->getKey(),
            'recorded_by' => $user->getKey(),
        ]);
    } else {
        Attachment::factory()->create([
            'attachable_id' => $record->getKey(),
            'profile_id' => $profile->getKey(),
            'recorded_by' => $user->getKey(),
        ]);
    }

    expect($record->fresh()->canBeDeleted())->toBeFalse();
    expect(fn () => app(DeleteRecord::class)->handle($user, $record->fresh()))
        ->toThrow(ValidationException::class);
})->with(['movement', 'return', 'attachment']);

test('a user cannot delete a record from another profile', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $record = $owner->financialProfiles()->firstOrFail()->records()->create([
        'title' => 'Private record',
        'note' => null,
        'is_archived' => false,
    ]);

    expect(fn () => app(DeleteRecord::class)->handle($other, $record))
        ->toThrow(AuthorizationException::class);
});

test('a promise route cannot cross profile tenancy', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = $profile->records()->create([
        'title' => 'Private promise',
        'note' => null,
        'is_archived' => false,
    ]);

    $this->actingAs($other)
        ->get(route('promises.show', [$profile, $record]))
        ->assertNotFound();
});
