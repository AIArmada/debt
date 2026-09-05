<?php

use App\Actions\Obligations\RecordTransaction;
use App\Actions\Records\CreateRecord;
use App\Models\AuditLog;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('sensitive obligation actions create audit records without secret values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();

    $record = app(CreateRecord::class)->handle($user, $profile, [
        'title' => 'Audited arrangement',
        'description' => '',
        'party_id' => null,
        'sensitivity' => 'private',
    ], [
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'tracking_mode' => 'snapshot',
        'title' => 'Audited record',
        'category' => 'personal_loan',
        'currency' => 'MYR',
        'original_amount' => null,
        'current_total_balance' => '100.00',
        'minimum_payment_amount' => null,
        'next_due_on' => null,
        'is_interest_bearing' => false,
        'description' => '',
        'subject_name' => null,
        'subject_quantity' => null,
        'subject_unit' => null,
        'subject_condition' => null,
        'subject_details' => null,
        'asset_type' => null,
        'service_type' => null,
        'estimated_value' => null,
        'estimated_value_currency' => null,
        'completion_criteria' => null,
        'is_conditional' => false,
        'condition_description' => null,
        'condition_triggered_on' => null,
    ]);
    $obligation = $record->obligations->firstOrFail();

    app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '25.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-03',
        'external_reference' => null,
        'note' => null,
    ]);

    expect(AuditLog::query()->count())->toBe(4);
    $this->assertDatabaseHas('audit_logs', ['auditable_type' => Record::class, 'auditable_id' => $record->id, 'action' => 'created']);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Obligation::class,
        'auditable_id' => $obligation->id,
        'action' => 'created',
    ]);
    $transactionLog = AuditLog::query()->where('auditable_type', 'App\\Models\\FinancialTransaction')->firstOrFail();
    expect($transactionLog->action)->toBe('created')
        ->and($transactionLog->after ?? [])->not->toHaveKey('provider_payload');
});
