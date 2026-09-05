<?php

use App\Actions\Obligations\CreateCollectionSchedule;
use App\Actions\Obligations\CreateObligation;
use App\Actions\Obligations\CreatePaymentSchedule;
use App\Actions\Obligations\UpdateObligation;
use App\Actions\Plans\GenerateRepaymentPlan;
use App\Domain\Planning\ProfileRepaymentSummary;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\User;
use App\Services\DueReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

test('dormant conditionals stay out of plan candidates until triggered', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $budget = $profile->budgetPeriods()->create([
        'currency' => 'MYR',
        'starts_on' => today()->toDateString(),
        'ends_on' => today()->addMonth()->toDateString(),
        'emergency_reserve_amount' => 0,
        'available_for_obligations_amount' => 0,
        'status' => 'open',
    ]);
    $dormant = createConditionalObligation($profile, 'Contingent guarantee');

    $candidates = app(ProfileRepaymentSummary::class)->candidateRows($budget);

    expect($candidates->pluck('obligation_id'))->not->toContain($dormant->id);

    try {
        app(GenerateRepaymentPlan::class)->handle($budget, 'smallest_balance', [$dormant->id]);
        $this->fail('A dormant conditional must not be eligible for a repayment plan.');
    } catch (ValidationException) {
        expect(true)->toBeTrue();
    }

    app(UpdateObligation::class)->handle($dormant, updatePayload($dormant, true, 'Land sale completes', today()->toDateString()));

    $candidates = app(ProfileRepaymentSummary::class)->candidateRows($budget->fresh());

    expect($candidates->pluck('obligation_id'))->toContain($dormant->id);
    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => 'App\\Models\\Obligation',
        'auditable_id' => $dormant->id,
        'action' => 'condition_triggered',
    ]);
});

test('schedules cannot be created for dormant conditionals', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $dormant = createConditionalObligation($profile, 'Contingent loan');

    try {
        app(CreatePaymentSchedule::class)->handle($dormant, [
            'mode' => 'manual',
            'amount' => '100',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'starts_on' => today()->toDateString(),
            'next_runs_on' => today()->toDateString(),
            'per_payment_limit' => '100',
        ]);
        $this->fail('A payment schedule must not be created for a dormant conditional.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('amount');
    }

    try {
        app(CreateCollectionSchedule::class)->handle($user, $dormant, [
            'mode' => 'manual_follow_up',
            'amount' => '100',
            'currency' => 'MYR',
            'frequency' => 'monthly',
            'starts_on' => today()->toDateString(),
            'next_due_on' => today()->toDateString(),
            'ends_on' => null,
            'collection_method' => 'bank_transfer',
            'collection_account_id' => null,
            'grace_days' => 0,
            'note' => null,
        ]);
        $this->fail('A collection schedule must not be created for a dormant conditional.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('amount');
    }
});

test('a conditional obligation requires a description', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $record = $profile->records()->create(['title' => 'Contingent arrangement']);

    try {
        app(CreateObligation::class)->handle($user, $record, obligationPayload(isConditional: true, description: null));
        $this->fail('A conditional obligation without a description must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('condition_description');
    }
});

test('due reminders skip dormant conditionals', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $profile = $user->financialProfiles()->firstOrFail();
    createConditionalObligation($profile, 'Contingent bill', nextDueOn: today()->addDay()->toDateString());

    expect(app(DueReminderService::class)->send())->toBe(0);
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $user->id]);
});

function createConditionalObligation(FinancialProfile $profile, string $title, ?string $nextDueOn = null): Obligation
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
        'minimum_payment_amount' => 10000,
        'next_due_on' => $nextDueOn,
        'data_confidence' => 'partial',
        'is_conditional' => true,
        'condition_description' => 'The land sale completes first.',
        'condition_triggered_on' => null,
    ]);
}

function obligationPayload(bool $isConditional, ?string $description): array
{
    return [
        'direction' => 'payable',
        'tracking_mode' => 'snapshot',
        'obligation_kind' => 'money',
        'title' => 'Contingent loan',
        'category' => 'personal_loan',
        'currency' => 'MYR',
        'original_amount' => null,
        'current_total_balance' => '500',
        'minimum_payment_amount' => null,
        'next_due_on' => null,
        'is_interest_bearing' => false,
        'description' => '',
        'subject_name' => null,
        'subject_quantity' => null,
        'quantity_mode' => null,
        'subject_unit' => null,
        'subject_condition' => null,
        'subject_details' => null,
        'asset_type' => null,
        'service_type' => null,
        'estimated_value' => null,
        'estimated_value_currency' => null,
        'completion_criteria' => null,
        'is_conditional' => $isConditional,
        'condition_description' => $description,
        'condition_triggered_on' => null,
    ];
}

function updatePayload(Obligation $obligation, bool $isConditional, ?string $description, ?string $triggeredOn): array
{
    return [
        'title' => $obligation->title,
        'tracking_mode' => 'snapshot',
        'category' => $obligation->category,
        'original_amount' => null,
        'current_principal_balance' => '500',
        'current_total_balance' => '500',
        'minimum_payment_amount' => null,
        'started_on' => null,
        'due_on' => null,
        'next_due_on' => null,
        'data_confidence' => 'partial',
        'is_interest_bearing' => false,
        'description' => '',
        'subject_name' => null,
        'subject_quantity' => null,
        'current_subject_quantity' => null,
        'quantity_mode' => null,
        'subject_unit' => null,
        'subject_condition' => null,
        'subject_details' => null,
        'asset_type' => null,
        'service_type' => null,
        'estimated_value' => null,
        'estimated_value_currency' => null,
        'completion_criteria' => null,
        'is_conditional' => $isConditional,
        'condition_description' => $description,
        'condition_triggered_on' => $triggeredOn,
    ];
}
