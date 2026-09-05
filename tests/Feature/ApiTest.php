<?php

use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ProfileMember;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

pest()->use(RefreshDatabase::class);

beforeEach(function (): void {
    $csrfToken = 'test-csrf-token';

    $this->withSession(['_token' => $csrfToken])
        ->withHeader('X-CSRF-TOKEN', $csrfToken);
});

test('session-authenticated api routes include csrf protection', function () {
    $middleware = Route::getRoutes()->getByName('api.v1.records.store')->gatherMiddleware();

    expect($middleware)->toContain(PreventRequestForgery::class);
});

test('invitation acceptance is rate limited', function () {
    $middleware = Route::getRoutes()->getByName('invitations.accept')->gatherMiddleware();

    expect($middleware)->toContain('throttle:invitations');
});

test('authenticated user can list and create records with nested obligations', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $existing = createApiObligation($profile, 'Existing balance');
    $party = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Aminah', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);

    $this->actingAs($user)->getJson(route('api.v1.records.index'))->assertOk()->assertJsonPath('data.0.id', $existing->record_id)->assertJsonPath('data.0.obligations.0.id', $existing->id);

    $this->actingAs($user)->postJson(route('api.v1.records.store'), ['profile_id' => $profile->id, 'title' => 'Family arrangement', 'description' => 'A mixed arrangement.', 'party_id' => $party->id, 'obligation' => ['direction' => 'receivable', 'obligation_kind' => 'money', 'category' => 'family_support', 'currency' => 'MYR', 'current_total_balance' => '200.0000', 'title' => 'New receivable']])->assertCreated()->assertJsonPath('data.title', 'Family arrangement')->assertJsonPath('data.parties.0.name', 'Aminah')->assertJsonPath('data.obligations.0.title', 'New receivable');
    $this->assertDatabaseHas('obligations', ['record_id' => Record::query()->where('title', 'Family arrangement')->value('id'), 'title' => 'New receivable']);
});

test('authenticated user can record a confirmed api transaction', function () {
    $user = User::factory()->create();
    $obligation = createApiObligation($user->financialProfiles()->firstOrFail(), 'API payment');
    $this->actingAs($user)->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), ['amount' => '40.0000', 'status' => 'confirmed', 'occurred_on' => '2026-09-03', 'external_reference' => 'API-001'])->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.obligation_balance', '60.00');
    $this->assertDatabaseHas('financial_transactions', ['obligation_id' => $obligation->id, 'external_reference' => 'API-001', 'status' => 'confirmed']);
});

test('api can record a payment that reverses the current position', function () {
    $user = User::factory()->create();
    $obligation = createApiObligation($user->financialProfiles()->firstOrFail(), 'API reversed position');
    $this->actingAs($user)->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), ['amount' => '125.0000', 'status' => 'confirmed', 'occurred_on' => '2026-09-04'])->assertCreated()->assertJsonPath('data.obligation_balance', '-25.00')->assertJsonPath('data.current_position.direction', 'receivable')->assertJsonPath('data.current_position.amount', '25.00')->assertJsonPath('data.current_position.label', 'They owe you')->assertJsonPath('data.current_position.is_reversed', true);
});

test('authenticated user can edit an api transaction', function () {
    $user = User::factory()->create();
    $obligation = createApiObligation($user->financialProfiles()->firstOrFail(), 'API editable payment');
    $this->actingAs($user)->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), ['amount' => '40.0000', 'status' => 'confirmed', 'occurred_on' => '2026-09-03'])->assertCreated();
    $transaction = $obligation->transactions()->firstOrFail();
    $this->actingAs($user)->patchJson(route('api.v1.records.transactions.update', [$obligation->record, $obligation, $transaction]), ['amount' => '25.0000', 'status' => 'confirmed', 'entry_type' => 'payment', 'occurred_on' => '2026-09-03'])->assertOk()->assertJsonPath('data.balance_before', '100.00')->assertJsonPath('data.balance_after', '75.00')->assertJsonPath('data.obligation_balance', '75.00');
});

test('api can add an obligation to an existing record', function () {
    $user = User::factory()->create();
    $record = createApiObligation($user->financialProfiles()->firstOrFail(), 'Original obligation')->record;
    $this->actingAs($user)->postJson(route('api.v1.records.obligations.store', $record), ['obligation' => ['direction' => 'payable', 'obligation_kind' => 'action', 'category' => 'promise', 'title' => 'Send signed documents', 'completion_criteria' => 'Send and confirm receipt.']])->assertCreated()->assertJsonCount(2, 'data.obligations')->assertJsonPath('data.obligations.1.obligation_kind', 'action');
});

test('api can start a detailed record without a balance and add an advance', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $response = $this->actingAs($user)->postJson(route('api.v1.records.store'), ['profile_id' => $profile->id, 'title' => 'API flexible arrangement', 'obligation' => ['direction' => 'payable', 'tracking_mode' => 'ledger', 'obligation_kind' => 'money', 'title' => 'Future advances', 'category' => 'personal_loan', 'currency' => 'MYR']])->assertCreated();
    $record = Record::query()->where('title', 'API flexible arrangement')->firstOrFail();
    $obligation = $record->obligations()->firstOrFail();
    $this->actingAs($user)->postJson(route('api.v1.records.transactions.store', [$record, $obligation]), ['entry_type' => 'advance', 'amount' => '100.0000', 'status' => 'confirmed', 'occurred_on' => '2026-09-04'])->assertCreated()->assertJsonPath('data.entry_type', 'advance')->assertJsonPath('data.balance_effect', 'increase')->assertJsonPath('data.obligation_balance', '100.00');
    expect($response->json('data.id'))->toBe($record->id);
});

test('api accepts an explicit minor amount for machine integrations', function () {
    $user = User::factory()->create();
    $obligation = createApiObligation($user->financialProfiles()->firstOrFail(), 'API minor amount');

    $this->actingAs($user)
        ->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), [
            'amount_minor' => 1250,
            'status' => 'confirmed',
            'occurred_on' => '2026-09-04',
        ])
        ->assertCreated()
        ->assertJsonPath('data.amount', '12.50')
        ->assertJsonPath('data.amount_minor', 1250);

    $this->assertDatabaseHas('financial_transactions', ['obligation_id' => $obligation->id, 'amount' => 1250]);
});

test('api can create an asset and record its return', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $this->actingAs($user)->postJson(route('api.v1.records.store'), ['profile_id' => $profile->id, 'title' => 'Borrowed vehicle arrangement', 'obligation' => ['direction' => 'payable', 'obligation_kind' => 'asset', 'title' => 'Borrowed vehicle', 'category' => 'borrowed_item', 'subject_name' => 'Toyota Vios', 'subject_quantity' => '1', 'quantity_mode' => 'countable', 'subject_unit' => 'vehicle', 'asset_type' => 'physical']])->assertCreated()->assertJsonPath('data.obligations.0.obligation_kind', 'asset');
    $record = Record::query()->where('title', 'Borrowed vehicle arrangement')->firstOrFail();
    $obligation = $record->obligations()->firstOrFail();
    $this->actingAs($user)->postJson(route('api.v1.records.events.store', [$record, $obligation]), ['event_type' => 'returned', 'quantity' => '1', 'occurred_on' => '2026-09-04'])->assertCreated()->assertJsonPath('data.event_type', 'returned')->assertJsonPath('data.obligation_status', 'settled');
});

test('api rejects unauthenticated and cross user access', function () {
    $owner = User::factory()->create();
    $obligation = createApiObligation($owner->financialProfiles()->firstOrFail(), 'Private API balance');
    $this->getJson(route('api.v1.records.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('api.v1.records.show', $obligation->record))->assertForbidden();
});

test('api does not expose records to a pending heir without activated emergency access', function () {
    $owner = User::factory()->create();
    $heir = User::factory()->create();
    $profile = $owner->financialProfiles()->firstOrFail();
    $obligation = createApiObligation($profile, 'Heir-only record');

    $member = new ProfileMember(['role' => 'heir', 'accepted_at' => now()]);
    $member->setAttribute('user_id', $heir->id);
    $profile->members()->save($member);

    $this->actingAs($heir)
        ->getJson(route('api.v1.records.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    expect($obligation->exists)->toBeTrue();
});

function createApiObligation(FinancialProfile $profile, string $title): Obligation
{
    return $profile->records()->create(['title' => $title.' arrangement', 'sensitivity' => 'private'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => $title, 'status' => 'active', 'currency' => 'MYR', 'current_principal_balance' => 10000, 'current_total_balance' => 10000, 'data_confidence' => 'verified']);
}
