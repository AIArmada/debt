<?php

use App\Actions\Obligations\CreateCollectionSchedule;
use App\Actions\Obligations\RecordObligationEvent;
use App\Actions\Obligations\RecordTransaction;
use App\Livewire\Collections\Accounts;
use App\Livewire\Obligations\ManageCollectionSchedule;
use App\Livewire\Obligations\ManageDeliveryInstructions;
use App\Livewire\Obligations\ManagePaymentInstructions;
use App\Livewire\Parties\Index as PartiesIndex;
use App\Livewire\Parties\ManageContactRoutes;
use App\Models\CollectionSchedule;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ObligationDeliveryInstruction;
use App\Models\ObligationPaymentInstruction;
use App\Models\PartyContact;
use App\Models\PartyContactRoute;
use App\Models\PartyPaymentDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function (): void {
    $csrfToken = 'test-csrf-token';

    $this->withSession(['_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken);
});

test('collection schedules are currency specific and only affected exposures pause', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = $profile->records()->create(['title' => 'Daily collection arrangement', 'sensitivity' => 'private']);
    $obligation = $record->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Mixed currency collection',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'currency_opening_balances' => ['MYR' => 10000, 'USD' => 10000],
        'currency_balances' => ['MYR' => 10000, 'USD' => 10000],
        'data_confidence' => 'partial',
    ]);

    Livewire::actingAs($user)
        ->test(Accounts::class, ['profile' => $profile])
        ->set('label', 'Maybank receiving account')
        ->set('method', 'bank_account')
        ->set('currency', 'MYR')
        ->set('accountIdentifier', '1122334455')
        ->call('save')
        ->assertHasNoErrors();

    $account = $profile->collectionAccounts()->firstOrFail();

    Livewire::actingAs($user)
        ->test(ManageCollectionSchedule::class, ['obligation' => $obligation])
        ->set('currency', 'MYR')
        ->set('amount', '5')
        ->set('collectionAccountId', $account->id)
        ->set('startsOn', today()->toDateString())
        ->set('nextDueOn', today()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $usdSchedule = app(CreateCollectionSchedule::class)->handle($user, $obligation, [
        'mode' => 'manual_follow_up',
        'amount' => '5',
        'currency' => 'USD',
        'frequency' => 'daily',
        'starts_on' => today()->toDateString(),
        'next_due_on' => today()->toDateString(),
        'ends_on' => null,
        'collection_method' => 'bank_transfer',
        'collection_account_id' => null,
        'grace_days' => 0,
        'note' => 'Separate USD exposure',
    ]);

    $myrSchedule = CollectionSchedule::query()->where('currency', 'MYR')->firstOrFail();
    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '0',
        'amount_minor' => 10000,
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => 'COLLECTION-MYR-001',
        'note' => 'Daily collection completed',
        'entry_type' => 'collection',
        'balance_effect' => null,
        'collection_schedule_id' => $myrSchedule->id,
    ]);

    expect($transaction->collection_schedule_id)->toBe($myrSchedule->id)
        ->and($myrSchedule->fresh()->status)->toBe('paused')
        ->and($usdSchedule->fresh()->status)->toBe('active')
        ->and($obligation->fresh()->current_total_balance)->toBe(0)
        ->and($obligation->fresh()->currencyBalances()['USD'])->toBe(10000);
});

test('money collection context does not change balance', function () {
    $user = User::factory()->create();
    $obligation = createMoneyObligation($user->financialProfiles()->firstOrFail(), 'Collection context');
    $before = $obligation->fresh()->current_total_balance;

    $event = app(RecordObligationEvent::class)->handle($user, $obligation, [
        'event_type' => 'missed',
        'quantity' => null,
        'occurred_on' => today()->toDateString(),
        'note' => 'The payer asked to postpone this week after an emergency.',
    ]);

    expect($event->event_type)->toBe('missed')
        ->and($obligation->fresh()->current_total_balance)->toBe($before);
    $this->assertDatabaseHas('obligation_events', ['id' => $event->id, 'event_type' => 'missed']);
});

test('a reversed payable can record a collection to clear the reversed position', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $obligation = createMoneyObligation($user->financialProfiles()->firstOrFail(), 'Reversed collection', 'payable');
    $obligation->forceFill([
        'current_total_balance' => -5000,
        'currency_balances' => ['MYR' => -5000],
    ])->save();

    $schedule = app(CreateCollectionSchedule::class)->handle($user, $obligation->fresh(), [
        'mode' => 'manual_follow_up',
        'amount' => '50',
        'currency' => 'MYR',
        'frequency' => 'daily',
        'starts_on' => today()->toDateString(),
        'next_due_on' => today()->toDateString(),
        'ends_on' => null,
        'collection_method' => 'cash',
        'collection_account_id' => null,
        'grace_days' => 0,
        'note' => null,
    ]);

    $transaction = app(RecordTransaction::class)->handle($obligation->fresh(), [
        'status' => 'confirmed',
        'amount' => '50',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => 'Refund received after overpayment',
        'entry_type' => 'collection',
        'balance_effect' => null,
        'collection_schedule_id' => $schedule->id,
    ]);

    expect($transaction->entry_type)->toBe('collection')
        ->and($transaction->balance_effect)->toBe('increase')
        ->and($obligation->fresh()->current_total_balance)->toBe(0)
        ->and($schedule->fresh()->status)->toBe('paused');
});

test('contact routes and delivery instructions can be saved from the ui', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $subject = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Principal', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);
    $intermediary = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Personal assistant', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);
    $contact = $intermediary->contacts()->create(['created_by_user_id' => $user->id, 'type' => 'phone', 'label' => 'Work mobile', 'value' => '+60123456789', 'purpose' => 'communication', 'is_primary' => true, 'is_message_safe' => true, 'visibility' => 'restricted']);
    $subject->contacts()->create(['created_by_user_id' => $user->id, 'type' => 'phone', 'label' => 'Primary', 'value' => '+60987654321', 'purpose' => 'communication', 'is_primary' => true, 'is_message_safe' => true, 'visibility' => 'restricted']);

    Livewire::actingAs($user)
        ->test(ManageContactRoutes::class, ['profile' => $profile])
        ->set('partyId', $subject->id)
        ->set('viaPartyId', $intermediary->id)
        ->set('viaContactId', $contact->id)
        ->set('relationshipType', 'personal_assistant')
        ->set('purpose', 'payment')
        ->set('isPrimary', true)
        ->set('instructions', 'Contact the assistant first and do not disclose the balance in a message.')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_contact_routes', [
        'party_id' => $subject->id,
        'via_party_id' => $intermediary->id,
        'via_contact_id' => $contact->id,
        'purpose' => 'payment',
        'is_primary' => true,
    ]);

    $route = PartyContactRoute::query()->where('party_id', $subject->id)->firstOrFail();
    Livewire::actingAs($user)
        ->test(ManageContactRoutes::class, ['profile' => $profile])
        ->call('view', $route->id)
        ->assertSet('viewingRouteId', $route->id)
        ->call('edit', $route->id)
        ->assertSet('editingRouteId', $route->id)
        ->assertDispatched('contact-route-editing')
        ->set('priority', 2)
        ->set('instructions', 'Updated route instructions.')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_contact_routes', [
        'id' => $route->id,
        'priority' => 2,
        'instructions' => 'Updated route instructions.',
    ]);

    $assetRecord = $profile->records()->create(['title' => 'Return borrowed vehicle', 'sensitivity' => 'private']);
    $assetObligation = $assetRecord->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Return vehicle',
        'status' => 'active',
        'subject_name' => 'Toyota Vios',
        'subject_quantity' => '1',
        'current_subject_quantity' => '1',
        'subject_unit' => 'vehicle',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
    ]);
    $address = $subject->addresses()->create([
        'created_by_user_id' => $user->id,
        'label' => 'Home',
        'address_line_1' => '12 Jalan Damai',
        'city' => 'Kuala Lumpur',
        'region' => 'W.P. Kuala Lumpur',
        'postal_code' => '50450',
        'country_code' => 'MY',
        'purpose' => 'delivery',
        'is_primary' => true,
        'visibility' => 'restricted',
    ]);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('editParty', $subject->id)
        ->assertSet('editingPartyId', $subject->id)
        ->assertSet('phone', '+60987654321')
        ->set('name', 'Principal updated')
        ->set('city', 'Petaling Jaya')
        ->call('updateParty')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('parties', ['id' => $subject->id, 'preferred_name' => 'Principal updated']);
    $this->assertDatabaseHas('party_addresses', ['id' => $address->id, 'city' => 'Petaling Jaya']);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('editContact', $contact->id)
        ->assertSet('editingContactId', $contact->id)
        ->set('contactLabel', 'Updated work mobile')
        ->set('contactValue', '+601122334455')
        ->call('addContact')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_contacts', ['id' => $contact->id, 'label' => 'Updated work mobile', 'value' => '+601122334455']);
    expect($contact->fresh())->toBeInstanceOf(PartyContact::class);

    Livewire::actingAs($user)
        ->test(ManageDeliveryInstructions::class, ['obligation' => $assetObligation])
        ->set('addressId', $address->id)
        ->set('recipientPartyId', $subject->id)
        ->set('label', 'Return to home')
        ->set('method', 'courier')
        ->set('instructions', 'Call before arrival and hand over to Principal only.')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('obligation_delivery_instructions', [
        'obligation_id' => $assetObligation->id,
        'address_id' => $address->id,
        'recipient_party_id' => $subject->id,
        'method' => 'courier',
        'status' => 'active',
    ]);
    expect($assetObligation->deliveryInstructions()->first())->toBeInstanceOf(ObligationDeliveryInstruction::class);
});

test('money obligations can record payment instructions with a destination snapshot', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $payee = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Lender', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);
    $destination = PartyPaymentDestination::create([
        'party_id' => $payee->id,
        'created_by_user_id' => $user->id,
        'method' => 'bank_account',
        'label' => 'Lender Maybank',
        'provider' => 'Maybank',
        'currency' => 'MYR',
        'account_holder_name' => 'Lender',
        'account_identifier_encrypted' => '1234567890',
        'account_identifier_last4' => '7890',
        'verification_status' => 'verified',
        'status' => 'active',
    ]);
    $obligation = $profile->records()->create(['title' => 'Payable arrangement', 'sensitivity' => 'private'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'snapshot',
        'category' => 'personal_loan',
        'title' => 'Payable loan',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 50000,
        'current_total_balance' => 50000,
        'data_confidence' => 'partial',
    ]);

    Livewire::actingAs($user)
        ->test(ManagePaymentInstructions::class, ['obligation' => $obligation])
        ->set('paymentDestinationId', $destination->id)
        ->set('beneficiaryPartyId', $payee->id)
        ->set('reference', 'March instalment')
        ->call('save')
        ->assertHasNoErrors();

    $instruction = $obligation->paymentInstructions()->firstOrFail();
    expect($instruction)->toBeInstanceOf(ObligationPaymentInstruction::class)
        ->and($instruction->shown_snapshot['destination_label'])->toBe('Lender Maybank')
        ->and($instruction->shown_snapshot['destination_masked'])->toBe($destination->maskedIdentifier())
        ->and($instruction->shown_snapshot['beneficiary'])->toBe('Lender');
    expect($instruction->shown_snapshot)->not->toHaveKey('account_identifier_encrypted');
    $this->assertDatabaseHas('obligation_payment_instructions', [
        'obligation_id' => $obligation->id,
        'payment_destination_id' => $destination->id,
        'currency' => 'MYR',
        'reference' => 'March instalment',
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test(ManagePaymentInstructions::class, ['obligation' => $obligation])
        ->call('archive', $instruction->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('obligation_payment_instructions', [
        'id' => $instruction->id,
        'status' => 'archived',
    ]);
});

test('payment instructions are rejected for non-money obligations', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Camera arrangement', 'sensitivity' => 'private'])->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Borrowed camera',
        'status' => 'active',
        'subject_name' => 'Camera',
        'subject_quantity' => '1',
        'current_subject_quantity' => '1',
        'quantity_mode' => 'countable',
        'subject_unit' => 'unit',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
    ]);

    Livewire::actingAs($user)
        ->test(ManagePaymentInstructions::class, ['obligation' => $obligation])
        ->call('save')
        ->assertHasErrors(['obligation']);

    expect($obligation->paymentInstructions()->count())->toBe(0);
});

test('payment destinations can be viewed edited and archived from the directory', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'created_by_user_id' => $user->id,
        'kind' => 'individual',
        'preferred_name' => 'Payment recipient',
        'status' => 'active',
        'verification_status' => 'unverified',
        'source' => 'test',
    ]);
    $destination = PartyPaymentDestination::create([
        'party_id' => $party->id,
        'created_by_user_id' => $user->id,
        'method' => 'bank_account',
        'label' => 'Old bank account',
        'provider' => 'Maybank',
        'currency' => 'MYR',
        'account_holder_name' => 'Payment recipient',
        'account_identifier_encrypted' => '1234567890',
        'account_identifier_last4' => '7890',
        'reference_template' => 'DEBT-001',
        'verification_status' => 'unverified',
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('viewPaymentDestination', $destination->id)
        ->assertSet('viewingPaymentDestinationId', $destination->id)
        ->call('viewPaymentDestination', $destination->id)
        ->assertSet('viewingPaymentDestinationId', null)
        ->call('editPaymentDestination', $destination->id)
        ->assertSet('editingPaymentDestinationId', $destination->id)
        ->assertSet('paymentAccountIdentifier', '')
        ->set('paymentLabel', 'Updated Maybank account')
        ->set('paymentAccountIdentifier', '9988776655')
        ->call('addPaymentDestination')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_payment_destinations', [
        'id' => $destination->id,
        'label' => 'Updated Maybank account',
        'account_identifier_last4' => '6655',
    ]);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('editPaymentDestination', $destination->id)
        ->set('paymentMethod', 'cash')
        ->assertSet('paymentAccountIdentifier', '')
        ->call('addPaymentDestination')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_payment_destinations', [
        'id' => $destination->id,
        'method' => 'cash',
        'account_identifier_encrypted' => null,
        'account_identifier_last4' => null,
    ]);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('archivePaymentDestination', $destination->id);

    $this->assertDatabaseHas('party_payment_destinations', [
        'id' => $destination->id,
        'status' => 'archived',
    ]);
});

test('editing a payment destination without re-entering the identifier keeps the saved secret', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $party = $profile->parties()->create([
        'created_by_user_id' => $user->id,
        'kind' => 'individual',
        'preferred_name' => 'Secret keeper',
        'status' => 'active',
        'verification_status' => 'unverified',
        'source' => 'test',
    ]);
    $destination = PartyPaymentDestination::create([
        'party_id' => $party->id,
        'created_by_user_id' => $user->id,
        'method' => 'bank_account',
        'label' => 'Original account',
        'provider' => 'Maybank',
        'currency' => 'MYR',
        'account_holder_name' => 'Secret keeper',
        'account_identifier_encrypted' => '1234567890',
        'account_identifier_last4' => '7890',
        'verification_status' => 'unverified',
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test(PartiesIndex::class)
        ->call('editPaymentDestination', $destination->id)
        ->assertSet('paymentAccountIdentifier', '')
        ->set('paymentLabel', 'Renamed account')
        ->call('addPaymentDestination')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('party_payment_destinations', [
        'id' => $destination->id,
        'label' => 'Renamed account',
        'account_identifier_last4' => '7890',
    ]);
    expect($destination->fresh()->account_identifier_encrypted)->toBe('1234567890');
});

test('api can create party routes collection schedules and delivery instructions', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $subject = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'API subject', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);
    $intermediary = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'API intermediary', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);
    $contact = $intermediary->contacts()->create(['created_by_user_id' => $user->id, 'type' => 'email', 'label' => 'Work email', 'value' => 'assistant@example.test', 'purpose' => 'communication', 'is_primary' => true, 'is_message_safe' => true, 'visibility' => 'restricted']);

    $this->actingAs($user)->postJson(route('api.v1.parties.contact-routes.store', $subject), [
        'via_party_id' => $intermediary->id,
        'via_contact_id' => $contact->id,
        'relationship_type' => 'representative',
        'purpose' => 'payment',
        'priority' => 1,
        'is_primary' => true,
    ])->assertCreated()->assertJsonPath('data.via_party_id', $intermediary->id);

    $this->actingAs($user)->postJson(route('api.v1.parties.payment-destinations.store', $subject), [
        'method' => 'bank_account',
        'label' => 'Maybank',
        'currency' => 'MYR',
        'account_identifier' => '1234567890',
    ])->assertCreated()->assertJsonPath('data.masked_identifier', '•••• 7890');

    $obligation = createMoneyObligation($profile, 'API collection', 'receivable');
    $this->actingAs($user)->postJson(route('api.v1.records.collection-schedules.store', [$obligation->record, $obligation]), [
        'mode' => 'bank_reconciliation',
        'amount' => '5.00',
        'currency' => 'MYR',
        'frequency' => 'daily',
        'starts_on' => today()->toDateString(),
        'next_due_on' => today()->toDateString(),
        'collection_method' => 'bank_transfer',
        'grace_days' => 1,
    ])->assertCreated()->assertJsonPath('data.amount_minor', 500);

    $assetRecord = $profile->records()->create(['title' => 'API delivery arrangement', 'sensitivity' => 'private']);
    $assetObligation = $assetRecord->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'asset', 'category' => 'borrowed_item', 'title' => 'API return item', 'status' => 'active', 'subject_name' => 'Laptop', 'subject_quantity' => '1', 'current_subject_quantity' => '1', 'subject_unit' => 'item', 'asset_type' => 'physical', 'data_confidence' => 'partial']);
    $address = $subject->addresses()->create(['created_by_user_id' => $user->id, 'label' => 'Office', 'address_line_1' => '1 Jalan Tun Razak', 'city' => 'Kuala Lumpur', 'country_code' => 'MY', 'purpose' => 'delivery', 'visibility' => 'restricted']);

    $this->actingAs($user)->postJson(route('api.v1.records.delivery-instructions.store', [$assetRecord, $assetObligation]), [
        'address_id' => $address->id,
        'recipient_party_id' => $subject->id,
        'method' => 'delivery',
        'label' => 'Office delivery',
        'instructions' => 'Call on arrival.',
    ])->assertCreated()->assertJsonPath('data.recipient_party_id', $subject->id);

    $this->assertDatabaseHas('party_contact_routes', ['party_id' => $subject->id, 'via_party_id' => $intermediary->id]);
    $this->assertDatabaseHas('obligation_delivery_instructions', ['obligation_id' => $assetObligation->id, 'address_id' => $address->id]);
    expect(PartyContactRoute::query()->where('party_id', $subject->id)->get())->toHaveCount(1);
});

function createMoneyObligation(FinancialProfile $profile, string $title, string $direction = 'receivable'): Obligation
{
    return $profile->records()->create(['title' => $title.' arrangement', 'sensitivity' => 'private'])->obligations()->create([
        'direction' => $direction,
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => $title,
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'currency_opening_balances' => ['MYR' => 10000],
        'currency_balances' => ['MYR' => 10000],
        'data_confidence' => 'verified',
    ]);
}
