<?php

use App\Livewire\Dashboard;
use App\Livewire\Documents\Upload as UploadDocument;
use App\Livewire\Obligations\Edit;
use App\Livewire\Obligations\EditTerms;
use App\Livewire\Obligations\RecordTransaction;
use App\Livewire\Profiles\Edit as EditProfile;
use App\Livewire\Profiles\Index as ProfilesIndex;
use App\Livewire\Records\Create;
use App\Livewire\Records\Edit as RecordEdit;
use App\Livewire\Records\Index as RecordsIndex;
use App\Models\Document;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ObligationTerm;
use App\Models\Record;
use App\Models\User;
use App\Rules\SafeUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('creating a user creates a personal profile', function () {
    $user = User::factory()->create();

    $this->assertDatabaseHas('financial_profiles', [
        'owner_user_id' => $user->id,
        'name' => 'Personal',
        'type' => 'personal',
    ]);
});

test('authenticated user can view an empty records page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('records.index'))
        ->assertOk()
        ->assertSee('No records match this view');
});

test('user can create a payable record', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $party = $profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Aminah', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('direction', 'payable')
        ->set('recordTitle', 'Family loan arrangement')
        ->set('obligationTitle', 'Family loan')
        ->set('category', 'personal_loan')
        ->set('partyMode', 'existing')
        ->set('primaryPartyId', $party->id)
        ->set('currency', 'MYR')
        ->set('currentTotalBalance', '1250.50')
        ->set('minimumPaymentAmount', '200.00')
        ->call('save')
        ->assertRedirect(route('records.show', Record::query()->firstOrFail()));

    $obligation = Obligation::query()->firstOrFail();
    expect($obligation->record->profile_id)->toBe($profile->id)
        ->and($obligation->record->primaryParty()?->preferred_name)->toBe('Aminah')
        ->and($obligation->getRawOriginal('current_total_balance'))->toBe(125050);
});

test('create review is explicit before a record is saved', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    $component = Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('recordTitle', 'Review-first arrangement')
        ->set('obligationTitle', 'Review-first loan')
        ->set('currentTotalBalance', '100.00')
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSet('showReview', true);

    expect(Record::query()->count())->toBe(0);

    $component->call('submit')->assertRedirect();
    expect(Record::query()->count())->toBe(1);
});

test('asset obligation can be created from the record form', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('profileId', $profile->id)
        ->set('obligationKind', 'asset')
        ->set('direction', 'receivable')
        ->set('recordTitle', 'Camera return arrangement')
        ->set('obligationTitle', 'Return camera set')
        ->set('subjectName', 'Mirrorless camera with lens')
        ->set('assetType', 'physical')
        ->set('subjectQuantity', '1')
        ->set('subjectUnit', 'camera set')
        ->set('subjectCondition', 'good')
        ->set('estimatedValue', '3500')
        ->set('estimatedValueCurrency', 'MYR')
        ->set('subjectDetails', 'Body, lens, batteries, charger, and strap.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('obligations', [
        'obligation_kind' => 'asset',
        'subject_name' => 'Mirrorless camera with lens',
        'current_subject_quantity' => '1.0000',
        'currency' => null,
    ]);
});

test('record edit updates primary party and visibility', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user);
    $party = $obligation->record->profile->parties()->create(['created_by_user_id' => $user->id, 'kind' => 'individual', 'preferred_name' => 'Nadia', 'status' => 'active', 'verification_status' => 'unverified', 'source' => 'test']);

    Livewire::actingAs($user)
        ->test(RecordEdit::class, ['record' => $obligation->record])
        ->set('title', 'Updated arrangement')
        ->set('description', 'Updated shared context.')
        ->set('primaryPartyId', $party->id)
        ->set('sensitivity', 'shared')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('records.show', $obligation->record));

    $record = $obligation->record->refresh();
    expect($record->title)->toBe('Updated arrangement')
        ->and($record->description)->toBe('Updated shared context.')
        ->and($record->sensitivity)->toBe('shared')
        ->and($record->primaryParty()?->preferred_name)->toBe('Nadia');
});

test('record filter uses the current money direction after reversal', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['current_total_balance' => -15000, 'currency_balances' => ['MYR' => -15000]]);

    Livewire::actingAs($user)
        ->test(RecordsIndex::class)
        ->set('direction', 'receivable')
        ->assertSee($obligation->record->title)
        ->set('direction', 'payable')
        ->assertDontSee($obligation->record->title);
});

test('record filter matches direction and kind on the same obligation', function () {
    $user = User::factory()->create();
    $moneyObligation = createDebtManagementObligation($user, ['title' => 'Payable component']);
    $record = $moneyObligation->record;

    $record->obligations()->create([
        'direction' => 'receivable',
        'obligation_kind' => 'asset',
        'category' => 'personal_loan',
        'title' => 'Receivable item',
        'status' => 'active',
        'subject_name' => 'Borrowed camera',
        'current_subject_quantity' => '1',
        'subject_unit' => 'item',
        'data_confidence' => 'verified',
    ]);

    Livewire::actingAs($user)
        ->test(RecordsIndex::class)
        ->set('direction', 'receivable')
        ->set('kind', 'asset')
        ->assertSee($record->title)
        ->set('kind', 'money')
        ->assertDontSee($record->title)
        ->set('direction', 'payable')
        ->set('kind', 'asset')
        ->assertDontSee($record->title)
        ->set('kind', 'money')
        ->assertSee($record->title);
});

test('user can view their obligation detail and transaction timeline', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['title' => 'Aminah loan']);

    $this->actingAs($user)->get(route('records.show', $obligation->record))
        ->assertOk()
        ->assertSee('Aminah loan')
        ->assertSee('Current position')
        ->assertSee('Add movement');
});

test('user cannot view an obligation from another profile', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $obligation = createDebtManagementObligation($owner);

    $this->actingAs($otherUser)->get(route('records.show', $obligation->record))
        ->assertForbidden();
});

test('user can edit their obligation without reassigning its profile', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createDebtManagementObligation($user);

    Livewire::actingAs($user)
        ->test(Edit::class, ['obligation' => $obligation])
        ->set('title', 'Updated family loan')
        ->set('currentTotalBalance', '0.0000')
        ->set('currentPrincipalBalance', '0.0000')
        ->set('dataConfidence', 'verified')
        ->call('save')
        ->assertRedirect(route('records.show', $obligation->record));

    $obligation->refresh();
    expect($obligation->title)->toBe('Updated family loan')
        ->and($obligation->record->profile_id)->toBe($profile->id)
        ->and($obligation->status)->toBe('settled')
        ->and($obligation->settled_at)->not->toBeNull();
});

test('user cannot edit an obligation from another profile', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $obligation = createDebtManagementObligation($owner);

    $this->actingAs($otherUser)->get(route('records.obligations.edit', [$obligation->record, $obligation]))
        ->assertForbidden();
});

test('user can create a business profile and it becomes selected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilesIndex::class)
        ->set('name', 'Northwind Trading')
        ->set('type', 'business')
        ->set('baseCurrency', 'USD')
        ->set('timezone', 'America/New_York')
        ->set('locale', 'en-US')
        ->set('isIslamicModeEnabled', true)
        ->call('save')
        ->assertHasNoErrors();

    $profile = FinancialProfile::query()->where('name', 'Northwind Trading')->firstOrFail();
    expect($profile->type)->toBe('business')
        ->and($profile->base_currency)->toBe('USD')
        ->and($profile->is_islamic_mode_enabled)->toBeTrue()
        ->and(session('selected_profile_id'))->toBe($profile->id);
});

test('user can edit profile settings', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(EditProfile::class, ['profile' => $profile])
        ->set('name', 'Household')
        ->set('type', 'family')
        ->set('baseCurrency', 'SGD')
        ->set('timezone', 'Asia/Singapore')
        ->call('save')
        ->assertRedirect(route('financial-profiles.index'));

    $profile->refresh();
    expect($profile->name)->toBe('Household')
        ->and($profile->type)->toBe('family')
        ->and($profile->base_currency)->toBe('SGD');
});

test('user cannot edit another users profile', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $owner->id)->firstOrFail();

    $this->actingAs($otherUser)->get(route('financial-profiles.edit', $profile))
        ->assertForbidden();
});

test('profile selection is persisted when changed on dashboard', function () {
    $user = User::factory()->create();
    $secondProfile = $user->financialProfiles()->create([
        'name' => 'Business',
        'type' => 'business',
        'base_currency' => 'USD',
        'timezone' => 'UTC',
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('profileId', $secondProfile->id)
        ->assertSet('profileId', $secondProfile->id);

    expect(session('selected_profile_id'))->toBe($secondProfile->id);
});

test('user can upload private evidence for an obligation', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user);
    $file = UploadedFile::fake()->create('loan-agreement.pdf', 100, 'application/pdf');

    Livewire::actingAs($user)
        ->test(UploadDocument::class, ['obligation' => $obligation])
        ->set('file', $file)
        ->set('category', 'agreement')
        ->set('verificationStatus', 'verified')
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::query()->firstOrFail();
    expect($document->category)->toBe('agreement')
        ->and($document->verification_status)->toBe('verified');
    $media = $document->mediaFile();
    expect($media)->not->toBeNull()
        ->and($media->getDownloadFilename())->toBe('loan-agreement.pdf');
    Storage::disk($media->disk)->assertExists($media->getPathRelativeToRoot());
    $this->assertDatabaseHas('media', [
        'model_type' => Document::class,
        'model_id' => $document->id,
        'collection_name' => Document::MEDIA_COLLECTION,
        'file_name' => 'loan-agreement.pdf',
    ]);
    $this->assertDatabaseHas('document_links', [
        'document_id' => $document->id,
        'record_id' => $obligation->record_id,
        'obligation_id' => null,
        'purpose' => 'evidence',
    ]);

    $this->actingAs($user)->get(route('documents.download', $document))
        ->assertDownload('loan-agreement.pdf');
});

test('user cannot download another users evidence', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $obligation = createDebtManagementObligation($owner);
    $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

    Livewire::actingAs($owner)
        ->test(UploadDocument::class, ['obligation' => $obligation])
        ->set('file', $file)
        ->call('save');

    $document = Document::query()->firstOrFail();

    $this->actingAs($otherUser)->get(route('documents.download', $document))
        ->assertForbidden();
});

test('evidence upload rejects executable file types', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user);
    $file = UploadedFile::fake()->create('installer.exe', 100, 'application/x-dosexec');

    Livewire::actingAs($user)
        ->test(UploadDocument::class, ['obligation' => $obligation])
        ->set('file', $file)
        ->call('save')
        ->assertHasErrors(['file']);

    expect(Document::query()->count())->toBe(0);
});

test('safe upload rejects extensionless hidden and compound active-content filenames', function () {
    foreach (['.htaccess', 'README', 'x.php.jpg'] as $filename) {
        $file = UploadedFile::fake()->create($filename, 100, 'image/jpeg');
        $validator = Validator::make(['file' => $file], ['file' => [new SafeUpload]]);

        expect($validator->fails())->toBeTrue();
    }
});

test('evidence upload accepts zip archives', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user);
    $file = UploadedFile::fake()->create('supporting-materials.zip', 100, 'application/zip');

    Livewire::actingAs($user)
        ->test(UploadDocument::class, ['obligation' => $obligation])
        ->set('file', $file)
        ->set('category', 'other')
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::query()->firstOrFail();
    expect($document->mediaFile()?->getDownloadFilename())->toBe('supporting-materials.zip');
});

test('user can save versioned terms and see a transparent estimate', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['current_total_balance' => 120000]);

    Livewire::actingAs($user)
        ->test(EditTerms::class, ['obligation' => $obligation])
        ->set('calculationMethod', 'simple_interest')
        ->set('interestRate', '12.00000000')
        ->set('interestPeriod', 'annual')
        ->set('sourceNote', 'Checked against the signed agreement.')
        ->set('sourceVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $term = ObligationTerm::query()->firstOrFail();
    expect($term->version)->toBe(1)
        ->and($term->calculation_method)->toBe('simple_interest')
        ->and($term->source_snapshot['note'])->toBe('Checked against the signed agreement.')
        ->and($term->source_snapshot['verified'])->toBeTrue();

    $this->actingAs($user)->get(route('records.show', $obligation->record))
        ->assertOk()
        ->assertSee('Estimated monthly interest')
        ->assertSee('12% interest');
});

test('fixed installment terms store a clear installment amount', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['minimum_payment_amount' => 30000]);

    Livewire::actingAs($user)
        ->test(EditTerms::class, ['obligation' => $obligation])
        ->set('calculationMethod', 'fixed_installment')
        ->set('fixedInstallmentAmount', '350.00')
        ->set('sourceNote', 'Agreed monthly instalment in the signed message.')
        ->call('save')
        ->assertHasNoErrors();

    $term = ObligationTerm::query()->firstOrFail();
    expect($term->fixed_installment_amount)->toBe(35000);
});

test('confirmed payable payment reduces the balance with decimal precision', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, [
        'direction' => 'payable',
        'current_total_balance' => 100012,
        'current_principal_balance' => 100012,
    ]);

    Livewire::actingAs($user)
        ->test(RecordTransaction::class, ['obligation' => $obligation])
        ->set('amount', '100.12')
        ->set('status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    $obligation->refresh();
    expect($obligation->getRawOriginal('current_total_balance'))->toBe(90000)
        ->and($obligation->getRawOriginal('current_principal_balance'))->toBe(90000)
        ->and($obligation->status)->toBe('active');
    $this->assertDatabaseHas('financial_transactions', [
        'obligation_id' => $obligation->id,
        'entry_type' => 'payment',
        'status' => 'confirmed',
        'amount' => 10012,
    ]);
});

test('confirmed receivable collection settles the obligation', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, [
        'direction' => 'receivable',
        'current_total_balance' => 25050,
    ]);

    Livewire::actingAs($user)
        ->test(RecordTransaction::class, ['obligation' => $obligation])
        ->set('amount', '250.50')
        ->set('status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    $obligation->refresh();
    expect($obligation->getRawOriginal('current_total_balance'))->toBe(0)
        ->and($obligation->status)->toBe('settled')
        ->and($obligation->settled_at)->not->toBeNull();
    $this->assertDatabaseHas('financial_transactions', [
        'obligation_id' => $obligation->id,
        'entry_type' => 'collection',
        'status' => 'confirmed',
    ]);
});

test('submitted transaction does not reduce the confirmed balance', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['current_total_balance' => 50000]);

    Livewire::actingAs($user)
        ->test(RecordTransaction::class, ['obligation' => $obligation])
        ->set('amount', '125.00')
        ->set('status', 'submitted')
        ->call('save')
        ->assertHasNoErrors();

    $obligation->refresh();
    expect($obligation->getRawOriginal('current_total_balance'))->toBe(50000)
        ->and($obligation->status)->toBe('active');
    $this->assertDatabaseHas('financial_transactions', [
        'obligation_id' => $obligation->id,
        'status' => 'submitted',
        'amount' => 12500,
    ]);
});

test('confirmed transaction can cross zero and expose a reversed position', function () {
    $user = User::factory()->create();
    $obligation = createDebtManagementObligation($user, ['current_total_balance' => 7500]);

    Livewire::actingAs($user)
        ->test(RecordTransaction::class, ['obligation' => $obligation])
        ->set('amount', '100.0000')
        ->set('status', 'confirmed')
        ->call('save')
        ->assertHasNoErrors();

    $transaction = $obligation->transactions()->firstOrFail();
    expect($transaction->balance_before)->toBe(7500)
        ->and($transaction->balance_after)->toBe(-2500)
        ->and($obligation->fresh()->getRawOriginal('current_total_balance'))->toBe(-2500)
        ->and($obligation->fresh()->isPositionReversed())->toBeTrue()
        ->and($obligation->fresh()->currentPositionDirection())->toBe('receivable')
        ->and($obligation->fresh()->currentPositionAmount())->toBe(2500);
});

/** @param array<string, mixed> $overrides */
function createDebtManagementObligation(User $user, array $overrides = []): Obligation
{
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    $record = $profile->records()->create(['title' => 'Test arrangement', 'sensitivity' => 'private']);

    return $record->obligations()->create(array_merge([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Personal loan',
        'status' => 'active',
        'currency' => 'MYR',
        'original_amount' => 100000,
        'current_principal_balance' => 100000,
        'current_total_balance' => 100000,
        'data_confidence' => 'verified',
        'is_interest_bearing' => false,
    ], $overrides));
}
