<?php

use App\Actions\Documents\UploadDocument;
use App\Actions\Obligations\RecordTransaction;
use App\Actions\Obligations\UpdateTransaction;
use App\Livewire\Documents\Upload as EvidenceUpload;
use App\Livewire\Obligations\Edit;
use App\Livewire\Records\Create;
use App\Models\Document;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('detailed record can start at zero without an original amount', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('trackingMode', 'ledger')
        ->set('recordTitle', 'Future advances arrangement')
        ->set('obligationTitle', 'Future advances')
        ->set('category', 'personal_loan')
        ->set('currentTotalBalance', null)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('records.show', Record::query()->firstOrFail()));

    $obligation = Obligation::query()->where('title', 'Future advances')->firstOrFail();
    expect($obligation->tracking_mode)->toBe('ledger')
        ->and($obligation->getRawOriginal('current_total_balance'))->toBe(0)
        ->and($obligation->original_amount)->toBeNull();
});

test('detailed ledger supports increases charges and decreases', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Flexible loan arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Flexible family loan',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $action = app(RecordTransaction::class);
    $advance = $action->handle($obligation, entry('advance', '250.0000'));
    $interest = $action->handle($obligation, entry('interest', '30.0000'));
    $payment = $action->handle($obligation, entry('payment', '80.0000'));

    $obligation->refresh();
    expect($advance->balance_after)->toBe(35000)
        ->and($interest->balance_after)->toBe(38000)
        ->and($payment->balance_after)->toBe(30000)
        ->and($obligation->getRawOriginal('current_total_balance'))->toBe(30000)
        ->and($obligation->getRawOriginal('current_principal_balance'))->toBe(27000);
    $this->assertDatabaseHas('financial_transactions', ['entry_type' => 'interest', 'interest_amount' => 3000]);
});

test('first snapshot movement promotes the record to ledger tracking', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Snapshot arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'snapshot',
        'category' => 'personal_loan',
        'title' => 'Snapshot starting point',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $transaction = app(RecordTransaction::class)->handle($obligation, entry('payment', '20.0000'));

    expect($obligation->fresh()->tracking_mode)->toBe('ledger')
        ->and($transaction->balance_before)->toBe(10000)
        ->and($transaction->balance_after)->toBe(8000);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'App\\Models\\Obligation',
        'auditable_id' => $obligation->id,
        'action' => 'tracking_mode_promoted',
    ]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['obligation' => $obligation->fresh()])
        ->set('trackingMode', 'snapshot')
        ->call('save')
        ->assertHasErrors(['trackingMode']);

    expect($obligation->fresh()->tracking_mode)->toBe('ledger');
});

test('adjustment requires an explicit direction and can reopen a settled record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Adjustment arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Adjustment test',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 5000,
        'data_confidence' => 'partial',
    ]);
    $action = app(RecordTransaction::class);

    try {
        $action->handle($obligation, entry('adjustment', '10.0000'));
        $this->fail('An adjustment without an effect should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('balance_effect');
    }

    $action->handle($obligation, entry('adjustment', '50.0000', 'decrease'));
    $obligation->refresh();
    expect($obligation->status)->toBe('settled');

    $action->handle($obligation, entry('advance', '25.0000'));
    expect($obligation->fresh()->status)->toBe('active')
        ->and($obligation->fresh()->getRawOriginal('current_total_balance'))->toBe(2500);
});

test('transaction evidence is distinct from general evidence', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Evidence arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Evidence test',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);
    $transaction = app(RecordTransaction::class)->handle($obligation, entry('payment', '20.0000'));

    app(UploadDocument::class)->handle(
        $user,
        $obligation,
        UploadedFile::fake()->create('payment-receipt.pdf', 100, 'application/pdf'),
        'receipt',
        'verified',
        $transaction,
    );

    $document = Document::query()->firstOrFail();
    expect($obligation->documents()->count())->toBe(0)
        ->and($transaction->documents()->firstOrFail()->original_filename)->toBe('payment-receipt.pdf');
    $this->assertDatabaseHas('document_links', [
        'document_id' => $document->id,
        'obligation_id' => null,
        'financial_transaction_id' => $transaction->id,
        'purpose' => 'transaction_evidence',
    ]);
});

test('editing a movement recalculates later balances and the obligation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Editable ledger arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Editable ledger',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $record = app(RecordTransaction::class);
    $advance = $record->handle($obligation, entry('advance', '250.0000'));
    $payment = $record->handle($obligation, entry('payment', '80.0000'));

    $updated = app(UpdateTransaction::class)->handle($obligation, $advance, entry('advance', '150.0000'));

    expect($updated->amount)->toBe(15000)
        ->and($updated->balance_before)->toBe(10000)
        ->and($updated->balance_after)->toBe(25000)
        ->and($payment->fresh()->balance_before)->toBe(25000)
        ->and($payment->fresh()->balance_after)->toBe(17000)
        ->and($obligation->fresh()->current_total_balance)->toBe(17000)
        ->and($obligation->fresh()->current_principal_balance)->toBe(17000);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'App\\Models\\FinancialTransaction',
        'auditable_id' => $advance->id,
        'action' => 'updated',
    ]);
});

test('editing a reversed collection preserves the reversed position', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Reversed edit arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Reversed edit',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $record = app(RecordTransaction::class);
    $record->handle($obligation, entry('payment', '150.0000'));
    $collection = $record->handle($obligation, entry('collection', '50.0000'));

    $updated = app(UpdateTransaction::class)->handle($obligation, $collection, [
        ...entry('collection', '50.0000'),
        'note' => 'Corrected collection note',
    ]);

    expect($updated->entry_type)->toBe('collection')
        ->and($updated->balance_effect)->toBe('increase')
        ->and($updated->balance_after)->toBe(0)
        ->and($obligation->fresh()->current_total_balance)->toBe(0)
        ->and($obligation->fresh()->status)->toBe('settled');
});

test('evidence can be a link or written note and can be scoped to a movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Scoped evidence arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Evidence forms',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);
    $transaction = app(RecordTransaction::class)->handle($obligation, entry('payment', '20.0000'));

    Livewire::actingAs($user)
        ->test(EvidenceUpload::class, ['obligation' => $obligation, 'transaction' => $transaction])
        ->set('evidenceType', 'link')
        ->set('title', 'Payment conversation')
        ->set('externalUrl', 'https://example.test/conversation/123')
        ->set('source', 'Private chat export')
        ->set('category', 'message')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test(EvidenceUpload::class, ['obligation' => $obligation, 'transaction' => $transaction])
        ->set('evidenceType', 'note')
        ->set('title', 'Witnessed handover')
        ->set('content', 'The payment was handed over in person and acknowledged.')
        ->set('category', 'witness_statement')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('documents', [
        'evidence_type' => 'link',
        'title' => 'Payment conversation',
        'external_url' => 'https://example.test/conversation/123',
    ]);
    $this->assertDatabaseHas('documents', [
        'evidence_type' => 'note',
        'title' => 'Witnessed handover',
        'content' => 'The payment was handed over in person and acknowledged.',
    ]);
    expect($transaction->documents()->count())->toBe(2);
    $this->assertDatabaseCount('document_links', 2);
    $this->assertDatabaseMissing('document_links', ['obligation_id' => $obligation->id]);
});

/** @return array{status: string, amount: string, currency: string, occurred_on: string, external_reference: null, note: null, entry_type: string, balance_effect: string|null} */
function entry(string $entryType, string $amount, ?string $balanceEffect = null): array
{
    return [
        'status' => 'confirmed',
        'amount' => $amount,
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => $entryType,
        'balance_effect' => $balanceEffect,
    ];
}
