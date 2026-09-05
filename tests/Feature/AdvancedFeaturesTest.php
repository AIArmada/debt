<?php

use App\Domain\Pawn\PawnRiskService;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Obligations\ManageSchedule;
use App\Livewire\Obligations\Scenarios;
use App\Livewire\Profiles\Edit as ProfileEdit;
use App\Models\BankImport;
use App\Models\BankImportRow;
use App\Models\EmergencyAccessRequest;
use App\Models\ExchangeRate;
use App\Models\PaymentExecutionAttempt;
use App\Models\PaymentSchedule;
use App\Models\PledgedAsset;
use App\Models\ProfileInvitation;
use App\Models\ProfileMember;
use App\Models\User;
use App\Services\DueReminderService;
use App\Services\Payments\ExecutePaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('exchange rate and shared profile navigation work', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'collaborator@example.test']);
    $profile = $owner->financialProfiles()->firstOrFail();
    $profile->exchangeRates()->create(['from_currency' => 'USD', 'to_currency' => 'MYR', 'rate' => '4.5000000000', 'source' => 'manual', 'effective_on' => today()]);
    $token = 'shared-profile-token';
    ProfileInvitation::create(['profile_id' => $profile->id, 'invited_by_user_id' => $owner->id, 'email' => $member->email, 'role' => 'viewer', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
    $this->actingAs($member)->get(route('invitations.accept', $token))->assertRedirect(route('financial-profiles.index'));

    $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertSee($profile->name);
    expect(ExchangeRate::query()->first())->not->toBeNull();
});

test('pawn risk is visible and automatic sandbox payment is idempotent', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Gold pledge arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'pawn_loan', 'title' => 'Gold pledge', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 40000, 'minimum_payment_amount' => 10000, 'data_confidence' => 'verified', 'due_on' => today()->addDays(3)]);
    PledgedAsset::create(['obligation_id' => $obligation->id, 'asset_type' => 'Gold', 'estimated_value' => 90000, 'currency' => 'MYR', 'matures_on' => today()->addDays(3), 'status' => 'pledged']);
    expect(app(PawnRiskService::class)->forProfile($profile))->toHaveCount(1);

    Livewire::actingAs($user)->test(ManageSchedule::class, ['obligation' => $obligation])->set('mode', 'automatic')->set('amount', '100')->set('startsOn', today()->toDateString())->set('nextRunsOn', today()->toDateString())->call('save')->assertHasNoErrors();
    $schedule = PaymentSchedule::query()->firstOrFail();
    Livewire::actingAs($user)->test(ManageSchedule::class, ['obligation' => $obligation])->call('authorise', $schedule->id)->assertHasNoErrors();
    $schedule->refresh();
    $attempt = app(ExecutePaymentSchedule::class)->handle($schedule);
    expect($attempt->status)->toBe('succeeded')
        ->and($obligation->fresh()->current_total_balance)->toBe(30000)
        ->and(app(ExecutePaymentSchedule::class)->handle($schedule)->id)->toBe($attempt->id)
        ->and(PaymentExecutionAttempt::query()->count())->toBe(1);
});

test('asset pawn risk uses the pledged asset currency without crashing', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Pledged item arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'asset',
        'category' => 'pawned_asset',
        'title' => 'Pledged bracelet',
        'status' => 'active',
        'subject_name' => 'Gold bracelet',
        'subject_quantity' => 1,
        'current_subject_quantity' => 1,
        'subject_unit' => 'piece',
        'data_confidence' => 'partial',
        'next_due_on' => today()->addDays(30),
    ]);
    PledgedAsset::create([
        'obligation_id' => $obligation->id,
        'asset_type' => 'Gold',
        'estimated_value' => 120000,
        'currency' => 'USD',
        'matures_on' => today()->addDays(30),
        'status' => 'pledged',
    ]);

    $risk = app(PawnRiskService::class)->forProfile($profile)->firstOrFail();
    expect($risk)->toMatchArray(['redemption_total' => 120000, 'redemption_currency' => 'USD', 'redemption_label' => 'Estimated item value']);
});

test('csv bank import can be reviewed and recorded', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Imported loan arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Imported loan', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 25000, 'data_confidence' => 'partial']);
    $file = UploadedFile::fake()->createWithContent('statement.csv', "date,description,amount,reference\n2026-09-03,Loan payment,-100.00,bank-1\n");
    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', $file)->call('runImport')->assertHasNoErrors();
    $row = BankImportRow::query()->firstOrFail();
    Livewire::actingAs($user)->test(ImportsIndex::class)->call('matchRow', $row->id, $obligation->id)->call('recordRow', $row->id)->assertHasNoErrors();
    expect($row->fresh()->status)->toBe('recorded')
        ->and($obligation->fresh()->current_total_balance)->toBe(15000)
        ->and(BankImport::query()->firstOrFail()->matched_count)->toBe(1);
    $import = BankImport::query()->firstOrFail();
    expect($import->original_filename)->toBe('statement.csv')
        ->and($import->mediaFile())->not->toBeNull();
    $this->assertDatabaseHas('media', [
        'model_type' => BankImport::class,
        'model_id' => $import->id,
        'collection_name' => BankImport::MEDIA_COLLECTION,
        'file_name' => 'statement.csv',
    ]);
});

test('csv bank import match can be cleared before recording', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Unmatchable loan arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Unmatchable loan', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 25000, 'data_confidence' => 'partial']);
    $file = UploadedFile::fake()->createWithContent('unmatch.csv', "date,description,amount,reference\n2026-09-03,Loan payment,-100.00,bank-2\n");

    Livewire::actingAs($user)->test(ImportsIndex::class)->set('file', $file)->call('runImport')->assertHasNoErrors();
    $row = BankImportRow::query()->firstOrFail();
    Livewire::actingAs($user)->test(ImportsIndex::class)->call('matchRow', $row->id, $obligation->id)->call('matchRow', $row->id, '')->assertHasNoErrors();

    $this->assertDatabaseHas('bank_import_rows', ['id' => $row->id, 'obligation_id' => null, 'status' => 'unmatched']);
});

test('heir invitation stays locked until owner activates it', function () {
    $owner = User::factory()->create();
    $heir = User::factory()->create(['email' => 'heir@example.test']);
    $profile = $owner->financialProfiles()->firstOrFail();
    $profile->records()->create(['title' => 'Private heir arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Private heir record', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 10000, 'data_confidence' => 'partial']);
    $token = 'heir-token';
    ProfileInvitation::create(['profile_id' => $profile->id, 'invited_by_user_id' => $owner->id, 'email' => $heir->email, 'role' => 'heir', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
    $this->actingAs($heir)->get(route('invitations.accept', $token))->assertRedirect(route('financial-profiles.index'));
    $this->actingAs($heir)->get(route('dashboard'))->assertOk()->assertDontSee('Private heir record');
    $request = EmergencyAccessRequest::query()->firstOrFail();
    Livewire::actingAs($owner)->test(ProfileEdit::class, ['profile' => $profile])->call('decideEmergencyAccess', $request->id, 'activate')->assertHasErrors('emergencyAccess');
    $request->update(['activate_after' => now()->subMinute()]);
    Livewire::actingAs($owner)->test(ProfileEdit::class, ['profile' => $profile])->call('decideEmergencyAccess', $request->id, 'activate')->assertHasNoErrors();
    $this->actingAs($heir)->get(route('dashboard'))->assertOk();
    expect(ProfileMember::query()->firstOrFail()->role)->toBe('heir')
        ->and($request->fresh()?->expires_at)->not->toBeNull();
});

test('scenarios and legal documents are available', function () {
    $user = User::factory()->create();
    $obligation = $user->financialProfiles()->firstOrFail()->records()->create(['title' => 'Scenario arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Scenario record', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 50000, 'minimum_payment_amount' => 5000, 'data_confidence' => 'partial']);
    Livewire::actingAs($user)->test(Scenarios::class, ['obligation' => $obligation])->set('extraPayment', '25')->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('calculation_scenarios', ['obligation_id' => $obligation->id]);
    foreach (['legal.privacy', 'legal.terms', 'legal.retention', 'legal.deletion'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Debt Management');
    }
});

test('reversed positions cannot create payment schedules or scenarios', function () {
    $user = User::factory()->create();
    $obligation = $user->financialProfiles()->firstOrFail()->records()->create(['title' => 'Reversed arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Reversed record',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => -15000,
        'currency_balances' => ['MYR' => -15000],
        'data_confidence' => 'partial',
    ]);

    Livewire::actingAs($user)
        ->test(ManageSchedule::class, ['obligation' => $obligation])
        ->set('amount', '100.00')
        ->call('save')
        ->assertHasErrors('amount');

    Livewire::actingAs($user)
        ->test(Scenarios::class, ['obligation' => $obligation])
        ->set('extraPayment', '100.00')
        ->call('save')
        ->assertHasErrors('extraPayment');
});

test('due reminder command creates a generic in app notification', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->records()->create(['title' => 'Due bill arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'bill', 'title' => 'Due bill', 'status' => 'active', 'currency' => 'MYR', 'current_total_balance' => 7500, 'next_due_on' => today()->addDay(), 'data_confidence' => 'verified']);
    expect(app(DueReminderService::class)->send())->toBe(1);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => 'App\\Notifications\\DebtDueNotification']);
});
