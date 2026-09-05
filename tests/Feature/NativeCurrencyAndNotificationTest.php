<?php

use App\Actions\Obligations\RecordTransaction;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('movement currencies remain separate without conversion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Mixed currency arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'ledger',
        'category' => 'personal_loan',
        'title' => 'Mixed currency obligation',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $entry = ['status' => 'confirmed', 'amount' => '25.0000', 'currency' => 'USD', 'occurred_on' => today()->toDateString(), 'external_reference' => null, 'note' => null, 'entry_type' => 'payment', 'balance_effect' => null];
    app(RecordTransaction::class)->handle($obligation, $entry);

    $fresh = $obligation->fresh();
    expect($fresh->current_total_balance)->toBe(10000)
        ->and($fresh->currencyBalances())->toMatchArray(['MYR' => 10000, 'USD' => -2500])
        ->and($fresh->currencyPosition('USD')['direction'])->toBe('receivable');
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => 'App\\Notifications\\ObligationActivityNotification']);
});

test('notification api lists and marks notifications read', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Notification arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Notification API record',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);
    app(RecordTransaction::class)->handle($obligation, ['status' => 'confirmed', 'amount' => '10.0000', 'currency' => 'MYR', 'occurred_on' => today()->toDateString(), 'external_reference' => null, 'note' => null]);
    $notification = $user->notifications()->firstOrFail();

    $this->actingAs($user)->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.id', $notification->id);
    $this->actingAs($user)->patchJson(route('api.v1.notifications.read', $notification->id))->assertOk()->assertJsonPath('data.read_at', fn ($value): bool => is_string($value));
    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('api rejects an unsupported currency code', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Currency arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => 'Currency validation',
        'status' => 'active',
        'currency' => 'MYR',
        'current_total_balance' => 10000,
        'data_confidence' => 'partial',
    ]);

    $this->actingAs($user)->postJson(route('api.v1.records.transactions.store', [$obligation->record, $obligation]), [
        'amount' => '10',
        'currency' => 'XYZ',
        'status' => 'confirmed',
    ])->assertUnprocessable()->assertJsonValidationErrors('currency');
});
