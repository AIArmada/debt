<?php

use App\Actions\Obligations\AddPledgedAsset;
use App\Actions\Obligations\RecordObligationEvent;
use App\Actions\Obligations\RecordTransaction;
use App\Actions\Obligations\UpdateTransaction;
use App\Actions\Profiles\InviteProfileMember;
use App\Livewire\Obligations\ManagePaymentInstructions;
use App\Livewire\Obligations\RecordTransaction as RecordTransactionForm;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ObligationPaymentInstruction;
use App\Models\PartyPaymentDestination;
use App\Models\User;
use App\Services\Payments\ExecutePaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('a payment movement can be linked to an active instruction of the same obligation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Linked payment');
    $instruction = createInstruction($user, $profile, $obligation);

    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => 'payment',
        'balance_effect' => null,
        'payment_instruction_id' => $instruction->id,
    ]);

    expect($transaction->payment_instruction_id)->toBe($instruction->id);
    $this->assertDatabaseHas('financial_transactions', [
        'id' => $transaction->id,
        'payment_instruction_id' => $instruction->id,
    ]);
});

test('an instruction from another obligation is rejected on a movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'First loan');
    $other = createInstructionMoneyObligation($profile, 'Second loan');
    $foreign = createInstruction($user, $profile, $other);

    try {
        app(RecordTransaction::class)->handle($obligation, [
            'status' => 'confirmed',
            'amount' => '100',
            'currency' => 'MYR',
            'occurred_on' => today()->toDateString(),
            'external_reference' => null,
            'note' => null,
            'entry_type' => 'payment',
            'balance_effect' => null,
            'payment_instruction_id' => $foreign->id,
        ]);
        $this->fail('A movement must not accept an instruction from another obligation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payment_instruction_id');
    }
});

test('dormant conditionals reject events assets and instructions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $money = createInstructionMoneyObligation($profile, 'Dormant money', dormant: true);
    $asset = $profile->records()->create(['title' => 'Dormant asset arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Dormant camera',
        'status' => 'active',
        'subject_name' => 'Camera',
        'subject_quantity' => '1.0000',
        'current_subject_quantity' => '1.0000',
        'subject_unit' => 'camera',
        'quantity_mode' => 'countable',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
        'is_conditional' => true,
        'condition_description' => 'The shoot is confirmed first.',
        'condition_triggered_on' => null,
    ]);

    try {
        app(RecordObligationEvent::class)->handle($user, $asset, ['event_type' => 'returned', 'quantity' => '1', 'occurred_on' => today()->toDateString(), 'note' => null]);
        $this->fail('A dormant asset must not accept fulfillment updates.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('event_type');
    }

    try {
        app(AddPledgedAsset::class)->handle($asset, ['asset_type' => 'Camera', 'description' => 'Body', 'quantity' => null, 'quantity_mode' => 'countable', 'quantity_unit' => null, 'estimated_value' => null, 'currency' => null, 'storage_location' => null, 'pledged_on' => null, 'matures_on' => null]);
        $this->fail('A dormant obligation must not accept pledged assets.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('asset_type');
    }

    Livewire::actingAs($user)
        ->test(ManagePaymentInstructions::class, ['obligation' => $money])
        ->call('save')
        ->assertHasErrors(['paymentDestinationId']);

    expect($money->fresh()->status)->toBe('active')
        ->and($asset->fresh()->status)->toBe('active');
});

test('a waived obligation counts as resolved on its record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $record = $profile->records()->create(['title' => 'Waived arrangement']);
    $obligation = $record->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Waived camera',
        'status' => 'active',
        'subject_name' => 'Camera',
        'subject_quantity' => '1.0000',
        'current_subject_quantity' => '1.0000',
        'subject_unit' => 'camera',
        'quantity_mode' => 'countable',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
    ]);

    app(RecordObligationEvent::class)->handle($user, $obligation, ['event_type' => 'waived', 'quantity' => null, 'occurred_on' => today()->toDateString(), 'note' => 'Owner forgave the return.']);

    expect($obligation->fresh()->status)->toBe('waived')
        ->and($record->fresh()->stateLabel())->toBe('All obligations resolved')
        ->and($record->openObligations()->count())->toBe(0);
});

test('profile invitations are throttled after ten recent sends', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    for ($i = 0; $i < 10; $i++) {
        app(InviteProfileMember::class)->handle($user, $profile, "guest{$i}@example.test", 'viewer');
    }

    try {
        app(InviteProfileMember::class)->handle($user, $profile, 'guest10@example.test', 'viewer');
        $this->fail('The eleventh recent invitation must be throttled.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email');
    }
});

test('invitation throttle counts across profiles for the same sender', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $first = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $second = FinancialProfile::factory()->create(['owner_user_id' => $user->id]);

    for ($i = 0; $i < 10; $i++) {
        app(InviteProfileMember::class)->handle($user, $first, "cross{$i}@example.test", 'viewer');
    }

    try {
        app(InviteProfileMember::class)->handle($user, $second, 'cross10@example.test', 'viewer');
        $this->fail('Invitations from the same sender must share one throttle budget.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email');
    }
});

test('editing a movement can attach a live instruction but not a stale one', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Editable movement loan');
    $live = createInstruction($user, $profile, $obligation);
    $stale = createInstruction($user, $profile, $obligation);
    PartyPaymentDestination::query()->whereKey($stale->payment_destination_id)->update(['status' => 'archived', 'superseded_at' => now()]);

    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    $updated = app(UpdateTransaction::class)->handle($obligation, $transaction, [
        'status' => 'confirmed',
        'amount' => '100',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => 'payment',
        'balance_effect' => null,
        'payment_instruction_id' => $live->id,
    ]);

    expect($updated->payment_instruction_id)->toBe($live->id);

    try {
        app(UpdateTransaction::class)->handle($obligation, $transaction->fresh(), [
            'status' => 'confirmed',
            'amount' => '100',
            'currency' => 'MYR',
            'occurred_on' => today()->toDateString(),
            'external_reference' => null,
            'note' => null,
            'entry_type' => 'payment',
            'balance_effect' => null,
            'payment_instruction_id' => $stale->id,
        ]);
        $this->fail('Editing a movement must not accept an instruction with an archived destination.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payment_instruction_id');
    }
});

test('a waived asset records how much was waived and cannot be waived twice', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $record = $profile->records()->create(['title' => 'Waived quantity arrangement']);
    $obligation = $record->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'borrowed_item',
        'title' => 'Waived tools',
        'status' => 'active',
        'subject_name' => 'Tools',
        'subject_quantity' => '3.0000',
        'current_subject_quantity' => '3.0000',
        'subject_unit' => 'set',
        'quantity_mode' => 'countable',
        'asset_type' => 'physical',
        'data_confidence' => 'partial',
    ]);

    $event = app(RecordObligationEvent::class)->handle($user, $obligation, ['event_type' => 'waived', 'quantity' => null, 'occurred_on' => today()->toDateString(), 'note' => 'Forgiven.']);

    expect($event->quantity)->toBe('3.0000')
        ->and($event->quantity_effect)->toBe('waived')
        ->and($obligation->fresh()->current_subject_quantity)->toBe('0.0000');

    try {
        app(RecordObligationEvent::class)->handle($user, $obligation->fresh(), ['event_type' => 'waived', 'quantity' => null, 'occurred_on' => today()->toDateString(), 'note' => null]);
        $this->fail('An already resolved obligation must not be waived again.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('event_type');
    }
});

test('a future schedule with no attempts returns nothing to execute', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Future schedule loan');
    $schedule = $obligation->paymentSchedules()->create([
        'mode' => 'automatic',
        'status' => 'active',
        'amount' => 10000,
        'currency' => 'MYR',
        'frequency' => 'monthly',
        'starts_on' => today()->toDateString(),
        'next_runs_on' => today()->addMonth()->toDateString(),
    ]);

    expect(app(ExecutePaymentSchedule::class)->handle($schedule))->toBeNull();
});

test('a new movement cannot use an instruction whose destination was archived', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Stale instruction loan');
    $instruction = createInstruction($user, $profile, $obligation);
    PartyPaymentDestination::query()->whereKey($instruction->payment_destination_id)->update(['status' => 'archived', 'superseded_at' => now()]);

    try {
        app(RecordTransaction::class)->handle($obligation, [
            'status' => 'confirmed',
            'amount' => '100',
            'currency' => 'MYR',
            'occurred_on' => today()->toDateString(),
            'external_reference' => null,
            'note' => null,
            'entry_type' => 'payment',
            'balance_effect' => null,
            'payment_instruction_id' => $instruction->id,
        ]);
        $this->fail('A movement must not accept an instruction pointing at an archived destination.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payment_instruction_id');
    }
});

test('movements linked before the archive keep their instruction proof', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Historic proof loan');
    $instruction = createInstruction($user, $profile, $obligation);
    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => 'payment',
        'balance_effect' => null,
        'payment_instruction_id' => $instruction->id,
    ]);

    PartyPaymentDestination::query()->whereKey($instruction->payment_destination_id)->update(['status' => 'archived', 'superseded_at' => now()]);

    expect($transaction->fresh()->payment_instruction_id)->toBe($instruction->id)
        ->and($instruction->fresh()->shown_snapshot['destination_masked'])->toBe('•••• 7890')
        ->and($instruction->fresh()->status)->toBe('active');
});

test('superseding archives the stale instruction and prefills its replacement', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Supersede loan');
    $instruction = createInstruction($user, $profile, $obligation);
    $instruction->update(['beneficiary_party_id' => $instruction->paymentDestination->party_id, 'reference' => 'March instalment']);
    PartyPaymentDestination::query()->whereKey($instruction->payment_destination_id)->update(['status' => 'archived', 'superseded_at' => now()]);
    $replacement = PartyPaymentDestination::create([
        'party_id' => $instruction->paymentDestination->party_id,
        'created_by_user_id' => $user->id,
        'method' => 'bank_account',
        'label' => 'Lender new account',
        'currency' => 'MYR',
        'account_holder_name' => 'Lender',
        'account_identifier_encrypted' => '0987654321',
        'account_identifier_last4' => '4321',
        'verification_status' => 'verified',
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test(ManagePaymentInstructions::class, ['obligation' => $obligation])
        ->call('supersede', $instruction->id)
        ->assertHasNoErrors()
        ->assertSet('paymentDestinationId', null)
        ->assertSet('reference', 'March instalment')
        ->set('paymentDestinationId', $replacement->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($instruction->fresh()->status)->toBe('archived');
    $this->assertDatabaseHas('obligation_payment_instructions', [
        'obligation_id' => $obligation->id,
        'payment_destination_id' => $replacement->id,
        'reference' => 'March instalment',
        'status' => 'active',
    ]);
});

test('clearing the instruction picker saves a movement with no link', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createInstructionMoneyObligation($profile, 'Unlinked picker loan');
    createInstruction($user, $profile, $obligation);

    Livewire::actingAs($user)
        ->test(RecordTransactionForm::class, ['obligation' => $obligation])
        ->set('amount', '50')
        ->set('paymentInstructionId', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('financial_transactions', [
        'obligation_id' => $obligation->id,
        'payment_instruction_id' => null,
    ]);
});

function createInstructionMoneyObligation(FinancialProfile $profile, string $title, bool $dormant = false): Obligation
{
    return $profile->records()->create(['title' => $title.' arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'snapshot',
        'category' => 'personal_loan',
        'title' => $title,
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 50000,
        'current_total_balance' => 50000,
        'data_confidence' => 'partial',
        'is_conditional' => $dormant,
        'condition_description' => $dormant ? 'The land sale completes first.' : null,
        'condition_triggered_on' => null,
    ]);
}

function createInstruction(User $user, FinancialProfile $profile, Obligation $obligation): ObligationPaymentInstruction
{
    $party = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Lender', 'status' => 'active', 'verification_status' => 'verified', 'source' => 'test']);
    $destination = PartyPaymentDestination::create([
        'party_id' => $party->id,
        'created_by_user_id' => $user->id,
        'method' => 'bank_account',
        'label' => 'Lender account',
        'currency' => 'MYR',
        'account_holder_name' => 'Lender',
        'account_identifier_encrypted' => '1234567890',
        'account_identifier_last4' => '7890',
        'verification_status' => 'verified',
        'status' => 'active',
    ]);

    return $obligation->paymentInstructions()->create([
        'payment_destination_id' => $destination->id,
        'currency' => 'MYR',
        'status' => 'active',
        'shown_snapshot' => ['destination_label' => 'Lender account', 'destination_masked' => '•••• 7890'],
    ]);
}
