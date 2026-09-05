<?php

use App\Actions\Obligations\RecordTransaction;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Obligations\RecordTransaction as RecordTransactionForm;
use App\Models\BankImportRow;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\DueReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('an obligation settles when its primary balance reaches zero despite foreign dust', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Multi-currency loan arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Multi-currency loan',
        'status' => 'active',
        'currency' => 'MYR',
        'current_principal_balance' => 10000,
        'current_total_balance' => 10000,
        'currency_opening_balances' => ['MYR' => 10000],
        'currency_balances' => ['MYR' => 10000, 'USD' => 5000],
        'data_confidence' => 'verified',
    ]);

    app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100',
        'currency' => 'MYR',
        'occurred_on' => today()->toDateString(),
        'external_reference' => null,
        'note' => null,
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    expect($obligation->fresh()->status)->toBe('settled')
        ->and($obligation->fresh()->currencyBalances()['USD'])->toBe(5000);
});

test('import matching rejects currency and direction mismatches', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $payable = $profile->records()->create(['title' => 'Payable arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Payable loan', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 25000, 'data_confidence' => 'partial']);
    $foreign = $profile->records()->create(['title' => 'Foreign arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Foreign loan', 'status' => 'active', 'currency' => 'USD', 'current_total_balance' => 25000, 'data_confidence' => 'partial']);
    $file = UploadedFile::fake()->createWithContent('mismatch.csv', "date,description,amount,reference\n2026-09-03,Collection in,100.00,bank-9\n");
    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', $file)->call('runImport')->assertHasNoErrors();
    $row = BankImportRow::query()->firstOrFail();

    Livewire::actingAs($user)->test(ImportsIndex::class)->call('matchRow', $row->id, $foreign->id)->assertHasErrors(['file']);
    expect($row->fresh()->status)->toBe('unmatched');

    Livewire::actingAs($user)->test(ImportsIndex::class)->call('matchRow', $row->id, $payable->id)->assertHasErrors(['file']);
    expect($row->fresh()->status)->toBe('unmatched');
});

test('re-uploading the same statement is rejected and skipped rows are reported', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $contents = "date,description,amount,reference\n2026-09-03,Loan payment,-100.00,bank-1\n2026-09-04,Unreadable,not-a-number,bank-2\n";

    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', UploadedFile::fake()->createWithContent('dup.csv', $contents))->call('runImport')->assertHasNoErrors();

    $import = $profile->bankImports()->firstOrFail();
    expect($import->row_count)->toBe(1)
        ->and($import->error_message)->toContain('1 row was skipped');

    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', UploadedFile::fake()->createWithContent('dup.csv', $contents))->call('runImport')->assertHasErrors(['file']);
    expect($profile->bankImports()->count())->toBe(1);
});

test('a statement without any usable rows is rejected', function () {
    $user = User::factory()->create();
    Livewire::actingAs($user)->test(ImportsIndex::class)
        ->set('file', UploadedFile::fake()->createWithContent('empty.csv', "date,description,amount\n2026-09-03,Nothing,garbage\n"))
        ->call('runImport')
        ->assertHasErrors(['file']);
});

test('a utf-8 bom does not break statement parsing', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    Livewire::actingAs($user)->test(ImportsIndex::class)
        ->set('file', UploadedFile::fake()->createWithContent('bom.csv', "\xEF\xBB\xBFdate,description,amount\n2026-09-03,Loan payment,-50.00\n"))
        ->call('runImport')
        ->assertHasNoErrors();

    expect($profile->bankImports()->firstOrFail()->row_count)->toBe(1);
});

test('due reminders are idempotent within a day and skipped when all channels are off', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->records()->create(['title' => 'Due arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'bill', 'title' => 'Due bill', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 7500, 'next_due_on' => today()->addDay(), 'data_confidence' => 'verified']);

    expect(app(DueReminderService::class)->send())->toBe(1)
        ->and(app(DueReminderService::class)->send())->toBe(0);

    $user->notificationPreference()->update(['email_enabled' => false, 'in_app_enabled' => false, 'push_enabled' => false]);
    $user->notifications()->delete();

    expect(app(DueReminderService::class)->send())->toBe(0);
    expect($user->notifications()->count())->toBe(0);
});

test('api exposes transactions budgets plans and imports with pagination', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Listed arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Listed loan', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 10000, 'data_confidence' => 'partial']);
    app(RecordTransaction::class)->handle($obligation, ['status' => 'confirmed', 'amount' => '10', 'currency' => 'MYR', 'occurred_on' => today()->toDateString(), 'external_reference' => null, 'note' => null, 'entry_type' => 'payment', 'balance_effect' => null]);
    $budget = $profile->budgetPeriods()->create(['currency' => 'MYR', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonth()->toDateString(), 'emergency_reserve_amount' => 0, 'available_for_obligations_amount' => 0, 'status' => 'open']);
    $plan = $profile->repaymentPlans()->create(['budget_period_id' => $budget->id, 'name' => 'Listed plan', 'strategy' => 'smallest_balance', 'available_amount' => 0, 'currency' => 'MYR', 'status' => 'active', 'generated_at' => now()]);
    $file = UploadedFile::fake()->createWithContent('listed.csv', "date,description,amount\n2026-09-03,Loan payment,-20.00\n");
    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', $file)->call('runImport')->assertHasNoErrors();

    $this->getJson(route('api.v1.records.transactions.index', [$obligation->record, $obligation]).'?per_page=10')
        ->assertOk()->assertJsonPath('data.0.entry_type', 'payment')->assertJsonPath('meta.total', 1);
    $this->getJson(route('api.v1.budget-periods.index', ['profile_id' => $profile->id]))
        ->assertOk()->assertJsonPath('data.0.id', $budget->id);
    $this->getJson(route('api.v1.repayment-plans.index', ['budget_period_id' => $budget->id]))
        ->assertOk()->assertJsonPath('data.0.id', $plan->id);
    $this->getJson(route('api.v1.bank-imports.index', ['profile_id' => $profile->id]))
        ->assertOk()->assertJsonPath('data.0.row_count', 1)->assertJsonPath('meta.total', 1);
    $this->actingAs(User::factory()->create())->getJson(route('api.v1.repayment-plans.index', ['budget_period_id' => $budget->id]))->assertNotFound();
});

test('zero-decimal currencies reject fractional movement amounts in the form', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Yen arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Yen loan', 'status' => 'active', 'currency' => 'JPY', 'current_total_balance' => 10000, 'data_confidence' => 'partial']);

    Livewire::actingAs($user)->test(RecordTransactionForm::class, ['obligation' => $obligation])
        ->set('currency', 'JPY')
        ->set('amount', '10.5')
        ->call('save')
        ->assertHasErrors(['amount']);

    Livewire::actingAs($user)->test(RecordTransactionForm::class, ['obligation' => $obligation])
        ->set('currency', 'JPY')
        ->set('amount', '10')
        ->call('save')
        ->assertHasNoErrors();
});

test('api rejects a movement that sends both amount and amount_minor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Exclusive arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Exclusive loan', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 10000, 'data_confidence' => 'partial']);

    $this->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), [
        'status' => 'confirmed', 'amount' => '10', 'amount_minor' => 1000, 'entry_type' => 'payment',
    ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});
