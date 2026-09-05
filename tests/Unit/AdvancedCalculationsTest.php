<?php

use App\Domain\Calculations\AdvancedObligationCalculator;
use App\Models\ObligationTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('compounding and late fee are projected in minor units', function () {
    $profile = User::factory()->create()->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Advanced arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Advanced calculation', 'currency' => 'MYR', 'current_total_balance' => 100000, 'minimum_payment_amount' => 0, 'due_on' => today()->subDay()->toDateString(), 'status' => 'active']);
    $term = new ObligationTerm(['calculation_method' => 'compound_interest', 'interest_rate' => '12.00000000', 'interest_period' => 'annual', 'compounding_period' => 'monthly', 'late_fee_amount' => 2000, 'late_fee_rate' => '1.00000000', 'formula' => ['grace_period_days' => 0]]);

    $result = (new AdvancedObligationCalculator)->project($obligation, $term, 1, '0');
    expect($result)->toMatchArray(['interest_total' => 1000, 'late_fee_total' => 3000, 'ending_balance' => 104000]);
});

test('extra payment scenario reduces projected ending balance', function () {
    $profile = User::factory()->create()->financialProfiles()->firstOrFail();
    $obligation = $profile->records()->create(['title' => 'Scenario arrangement'])->obligations()->create(['direction' => 'payable', 'obligation_kind' => 'money', 'category' => 'personal_loan', 'title' => 'Scenario calculation', 'currency' => 'MYR', 'current_total_balance' => 100000, 'minimum_payment_amount' => 10000, 'status' => 'active']);
    $term = new ObligationTerm(['calculation_method' => 'none']);
    $result = (new AdvancedObligationCalculator)->project($obligation, $term, 3, '50.0000');
    expect($result)->toMatchArray(['ending_balance' => 55000, 'payments_total' => 45000]);
});
