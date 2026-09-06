<?php

use App\Actions\Promises\CompleteCommitment;
use App\Actions\Promises\CorrectQuantityReturn;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CompleteCommitmentData;
use App\Actions\Promises\Data\CorrectQuantityReturnData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\Data\SaveNoteData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\ReturnQuantity;
use App\Actions\Promises\SaveNote;
use App\Actions\Promises\SettleObligation;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Money\Money;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\OutstandingQuantity;
use App\Livewire\Parties\Index as PeopleComponent;
use App\Livewire\Promises\Create as CreatePromiseComponent;
use App\Models\ActivityEntry;
use App\Models\CommitmentSubject;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('guests cannot open a profile promise list', function () {
    $profile = FinancialProfile::factory()->create();

    $this->get(route('promises.index', $profile))->assertRedirect(route('login'));
});

test('people is nested under an accessible profile', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('people.index', $profile))
        ->assertOk();

    $this->actingAs($otherUser)
        ->get(route('people.index', $profile))
        ->assertNotFound();
});

test('people creation uses the typed party kind through the action', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    Livewire::test(PeopleComponent::class, ['profile' => $profile])
        ->set('name', 'Northwind')
        ->set('kind', PartyKind::Organization->value)
        ->call('save');

    $this->assertDatabaseHas('parties', [
        'profile_id' => $profile->getKey(),
        'display_name' => 'Northwind',
        'kind' => PartyKind::Organization->value,
    ]);
});

test('a user can save a payable promise through the shared livewire action', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    Livewire::test(CreatePromiseComponent::class, ['profile' => $profile])
        ->set('partyName', 'Ali')
        ->set('direction', 'payable')
        ->set('amount', '50.00')
        ->set('note', 'Lunch advance')
        ->call('save')
        ->assertRedirect();

    $record = Record::query()->firstOrFail();
    $obligation = Obligation::query()->firstOrFail();

    $this->assertDatabaseHas('parties', ['display_name' => 'Ali', 'status' => 'active']);
    $this->assertDatabaseHas('money_movements', [
        'obligation_id' => $obligation->id,
        'entry' => MoneyEntry::OpeningBalance->value,
        'amount_minor' => 5000,
        'currency' => 'MYR',
        'status' => MovementStatus::Confirmed->value,
    ]);
    $this->assertDatabaseHas('activity_entries', [
        'subject_type' => Record::class,
        'subject_id' => $record->id,
        'action' => 'promise_created',
    ]);

    $this->get(route('promises.index', $profile))
        ->assertOk()
        ->assertSee('50.00')
        ->assertSee('You owe them');
});

test('counterparty resolution reuses an active exact match case insensitively', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'kind' => PartyKind::Individual,
        'display_name' => 'Aminah',
        'status' => PartyStatus::Active,
    ]);
    $this->actingAs($user);

    app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => '  aminah ',
        'partyId' => null,
        'direction' => Direction::Receivable->value,
        'amount' => '10.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));

    expect($profile->parties()->count())->toBe(1);
    $this->assertDatabaseHas('record_parties', [
        'party_id' => $party->id,
        'role' => PartyRole::Counterparty->value,
        'is_primary' => true,
    ]);
});

test('partial and full settlement derive status and balance from confirmed movements', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Nadia',
        'partyId' => null,
        'direction' => Direction::Receivable->value,
        'amount' => '100.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::settlement(2550, 'MYR', today()->toDateString(), 'First instalment'));

    expect(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => 7450])
        ->and($obligation->fresh()->status)->toBe(ObligationStatus::Open);

    app(SettleObligation::class)->handle($user, $obligation->fresh(), SettleObligationData::fromInput([]));

    expect(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => 0])
        ->and($obligation->fresh()->status)->toBe(ObligationStatus::Settled);
});

test('a new advance reopens a settled promise', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Reopened promise',
        'partyId' => null,
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    app(SettleObligation::class)->handle($user, $obligation, SettleObligationData::fromInput([]));
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Settled);

    app(RecordMoneyMovement::class)->handle($user, $obligation->fresh(), RecordMovementData::confirmed(
        1000,
        'MYR',
        MoneyEntry::Advance,
        today()->toDateString(),
    ));

    expect($obligation->fresh()->status)->toBe(ObligationStatus::Open)
        ->and(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => 1000]);
});

test('an overpayment reverses the current position without a dashboard special case', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Farah',
        'partyId' => null,
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::confirmed(
        15000,
        'MYR',
        MoneyEntry::Payment,
        today()->toDateString(),
        null,
    ));

    expect(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => -5000]);
    $this->get(route('promises.show', [$profile, $record]))
        ->assertOk()
        ->assertSee('They owe you');
});

test('voiding a movement keeps the movement and records the correction', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Johan',
        'partyId' => null,
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $movement = app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::settlement(2500, 'MYR', today()->toDateString()));

    app(VoidMovement::class)->handle($user, $movement, VoidMovementData::fromInput(['reason' => 'Wrong amount']));

    $this->assertDatabaseHas('money_movements', [
        'id' => $movement->id,
        'status' => MovementStatus::Voided->value,
        'void_reason' => 'Wrong amount',
    ]);
    $this->assertDatabaseHas('activity_entries', [
        'subject_id' => $movement->id,
        'action' => 'money_movement_voided',
    ]);
    expect(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => 10000]);
    expect($obligation->fresh()->status)->toBe(ObligationStatus::Open);
});

test('a promise from another profile is not found through scoped binding', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($owner, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Private person',
        'partyId' => null,
        'direction' => Direction::Payable->value,
        'amount' => '10.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));

    $this->actingAs($otherUser)
        ->get(route('promises.show', [$profile, $record]))
        ->assertNotFound();
});

test('a user cannot resolve a record from another accessible profile under the wrong profile URL', function () {
    $user = User::factory()->create();
    $firstProfile = $user->financialProfiles()->firstOrFail();
    $secondProfile = $user->financialProfiles()->create([
        'name' => 'Second profile',
        'base_currency' => 'MYR',
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);
    $record = $secondProfile->records()->create([
        'title' => 'Second profile promise',
        'note' => null,
        'is_archived' => false,
    ]);

    $this->actingAs($user)
        ->get(route('promises.show', [$firstProfile, $record]))
        ->assertNotFound();
});

test('native currency exposures are never summed together', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Multicurrency person',
        'partyId' => null,
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
        'dueOn' => null,
        'note' => null,
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();

    app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::confirmed(
        5000,
        'USD',
        MoneyEntry::Advance,
        today()->toDateString(),
        null,
    ));

    expect(app(OutstandingBalance::class)->forObligation($obligation->fresh()))->toBe(['MYR' => 10000, 'USD' => 5000]);
});

test('money parsing rejects fractional values for zero decimal currencies', function () {
    expect(fn () => Money::parseMajor('10.50', 'JPY'))
        ->toThrow(InvalidArgumentException::class, 'JPY supports 0 decimal places.');
});

test('notes, quantity returns, and commitment completion use dedicated actions', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user);

    $record = $profile->records()->create(['title' => 'Action coverage', 'note' => null, 'is_archived' => false]);
    $quantityObligation = $record->obligations()->create([
        'direction' => Direction::Receivable,
        'title' => 'Quantity return',
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Quantity,
    ]);
    $quantityObligation->quantitySubject()->create([
        'name' => 'Books',
        'total' => '3.0000',
        'unit' => 'items',
        'is_fractionable' => false,
    ]);
    $commitmentObligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => 'Commitment completion',
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Commitment,
    ]);
    $commitmentObligation->commitmentSubject()->create(['done_criteria' => 'Send the files.']);

    app(SaveNote::class)->handle($user, $record, SaveNoteData::fromInput(['note' => 'Updated context.']));
    $return = app(ReturnQuantity::class)->handle($user, $quantityObligation, RecordQuantityReturnData::fromInput([
        'quantity' => '3',
        'returnedOn' => today()->toDateString(),
        'note' => 'All returned.',
    ]));
    $commitment = app(CompleteCommitment::class)->handle($user, $commitmentObligation, CompleteCommitmentData::fromInput([
        'note' => 'Files sent.',
    ]));

    expect($record->fresh()->note)->toBe('Updated context.')
        ->and($return)->toBeInstanceOf(QuantityReturn::class)
        ->and(app(OutstandingQuantity::class)->forObligation($quantityObligation->fresh()))->toMatchArray([
            'total' => '3.0000',
            'returned' => '3.0000',
            'remaining' => '0.0000',
        ])
        ->and($quantityObligation->fresh()->status)->toBe(ObligationStatus::Settled)
        ->and($commitment)->toBeInstanceOf(CommitmentSubject::class)
        ->and($commitmentObligation->fresh()->status)->toBe(ObligationStatus::Settled)
        ->and(ActivityEntry::query()->where('profile_id', $profile->getKey())->whereIn('action', [
            'record_note_saved',
            'quantity_return_recorded',
            'commitment_completed',
        ])->count())->toBe(3);
});

test('quantity corrections void the original and append a replacement', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Quantity correction', 'note' => null, 'is_archived' => false]);
    $obligation = $record->obligations()->create([
        'direction' => Direction::Payable,
        'title' => $record->title,
        'status' => ObligationStatus::Open,
        'subject_type' => SubjectType::Quantity,
    ]);
    $obligation->quantitySubject()->create([
        'name' => 'Camera',
        'total' => '2.0000',
        'unit' => 'items',
        'is_fractionable' => false,
    ]);

    $return = app(ReturnQuantity::class)->handle($user, $obligation, RecordQuantityReturnData::fromInput([
        'quantity' => '2',
        'returnedOn' => today()->toDateString(),
        'note' => 'Original count.',
    ]));
    $replacement = app(CorrectQuantityReturn::class)->handle($user, $return, CorrectQuantityReturnData::fromInput([
        'quantity' => '1',
        'returnedOn' => today()->toDateString(),
        'note' => 'Corrected count.',
        'reason' => 'One item was missing.',
    ]));

    expect($replacement->id)->not->toBe($return->id)
        ->and($return->fresh()->status)->toBe(MovementStatus::Voided)
        ->and(app(OutstandingQuantity::class)->forObligation($obligation->fresh())['remaining'])->toBe('1.0000')
        ->and($obligation->fresh()->status)->toBe(ObligationStatus::Open);
});
