<?php

use App\Actions\Obligations\RecordTransaction;
use App\Actions\Obligations\UpdateTransaction;
use App\Domain\Money\MoneyAmount;
use App\Domain\Planning\BudgetCapacity;
use App\Domain\Planning\ProfileRepaymentSummary;
use App\Livewire\Plans\Index;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\RepaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('budget capacity excludes non essential expenses and plan covers minimums first', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $smallBalance = createPlanningObligation($profile, 'Small balance', '300.0000', '100.0000');
    $largeBalance = createPlanningObligation($profile, 'Large balance', '800.0000', '200.0000');

    $component = Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->set('emergencyReserveAmount', '100.0000')
        ->call('createBudget')
        ->assertHasNoErrors();

    $component
        ->set('cashFlowType', 'income')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->set('cashFlowAmount', '2000.0000')
        ->call('addCashFlow')
        ->set('cashFlowType', 'expense')
        ->set('cashFlowName', 'Rent')
        ->set('cashFlowCategory', 'housing')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowEssential', true)
        ->call('addCashFlow')
        ->set('cashFlowName', 'Dining out')
        ->set('cashFlowCategory', 'lifestyle')
        ->set('cashFlowAmount', '500.0000')
        ->set('cashFlowEssential', false)
        ->call('addCashFlow')
        ->set('strategy', 'smallest_balance')
        ->call('generatePlan')
        ->assertHasNoErrors();

    $budget = BudgetPeriod::query()->firstOrFail();
    $plan = RepaymentPlan::query()->with('allocations')->firstOrFail();
    expect($plan->available_amount)->toBe(90000)
        ->and($plan->allocations)->toHaveCount(2)
        ->and($plan->allocations[0]->obligation_id)->toBe($smallBalance->id)
        ->and($plan->allocations[0]->total_amount)->toBe(30000)
        ->and($plan->allocations[1]->total_amount)->toBe(60000)
        ->and($plan->budget_period_id)->toBe($budget->id)
        ->and($plan->allocations[0]->priority_reason)->toBe('Smallest current balance first.')
        ->and($budget->cashFlowEntries()->where('name', 'Rent')->value('amount'))->toBe(100000)
        ->and($plan->allocations[1]->obligation_id)->toBe($largeBalance->id);
});

test('plan is currency scoped and a payment updates allocation progress', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $myr = createPlanningObligation($profile, 'Rent support', '800.0000', '100.0000');
    $usd = createPlanningObligation($profile, 'Overseas purchase', '500.0000', '50.0000');
    $usd->update([
        'currency' => 'USD',
        'current_principal_balance' => MoneyAmount::fromMajorOrZero('500.0000', 'USD'),
        'current_total_balance' => MoneyAmount::fromMajorOrZero('500.0000', 'USD'),
        'minimum_payment_amount' => MoneyAmount::fromMajorOrZero('50.0000', 'USD'),
    ]);

    Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertSee('Choose what this plan covers')
        ->assertHasNoErrors();

    $budget = BudgetPeriod::query()->firstOrFail();
    $plan = RepaymentPlan::query()->with('allocations')->firstOrFail();
    expect($plan->allocations->contains('obligation_id', $myr->id))->toBeTrue()
        ->and($plan->allocations->contains('obligation_id', $usd->id))->toBeFalse()
        ->and($plan->allocations->first()->currency)->toBe('MYR');

    $transaction = app(RecordTransaction::class)->handle($myr, [
        'status' => 'confirmed',
        'amount' => '25.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-05',
        'external_reference' => null,
        'note' => 'September payment',
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    expect($transaction->repayment_plan_allocation_id)->toBe($plan->allocations->first()->id);
    $progress = app(ProfileRepaymentSummary::class)->planProgress($plan->fresh());
    expect($progress->first()['paid'])->toBe(2500)
        ->and($progress->first()['remaining'])->toBe($plan->allocations->first()->total_amount - 2500)
        ->and($plan->budget_period_id)->toBe($budget->id);
});

test('confirmed payment updates plan progress and budget utilisation separately', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createPlanningObligation($profile, 'Monthly support', '300.0000', '50.0000');

    Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-05',
        'external_reference' => null,
        'note' => 'September payment',
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    $budget = BudgetPeriod::query()->firstOrFail();
    $plan = RepaymentPlan::query()->firstOrFail();
    $progress = app(ProfileRepaymentSummary::class)->planProgress($plan->fresh());
    $capacity = app(BudgetCapacity::class)->breakdown($budget->fresh());

    expect($transaction->repayment_plan_allocation_id)->toBe($plan->allocations()->firstOrFail()->id)
        ->and($plan->fresh()->status)->toBe('active')
        ->and($progress->first()['paid'])->toBe(10000)
        ->and($progress->first()['remaining'])->toBe(20000)
        ->and($capacity)->toMatchArray(['safe_capacity' => 100000, 'actual_repayments' => 10000, 'available_to_plan' => 90000])
        ->and($obligation->fresh()->current_total_balance)->toBe(20000);
});

test('confirmed charge marks the current plan for review without changing budget capacity', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createPlanningObligation($profile, 'Chargeable arrangement', '300.0000', '50.0000');

    Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '20.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-06',
        'external_reference' => null,
        'note' => 'Interest added',
        'entry_type' => 'interest',
        'balance_effect' => null,
    ]);

    $plan = RepaymentPlan::query()->firstOrFail()->fresh();
    $budget = BudgetPeriod::query()->firstOrFail();
    $capacity = app(BudgetCapacity::class)->breakdown($budget->fresh());

    expect($plan->status)->toBe('needs_review')
        ->and($plan->review_reason)->toBe('A new charge or balance adjustment changed this plan\'s assumptions.')
        ->and($capacity)->toMatchArray(['safe_capacity' => 100000, 'actual_repayments' => 0, 'available_to_plan' => 100000]);
});

test('movement in a separate currency does not review a plan in its own currency', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createPlanningObligation($profile, 'Multi-currency arrangement', '300.0000', '50.0000');

    Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '10.0000',
        'currency' => 'USD',
        'occurred_on' => '2026-09-06',
        'external_reference' => null,
        'note' => 'Separate currency advance',
        'entry_type' => 'advance',
        'balance_effect' => null,
    ]);

    expect(RepaymentPlan::query()->firstOrFail()->fresh()->status)->toBe('active')
        ->and($obligation->fresh()->current_total_balance)->toBe(30000)
        ->and($obligation->fresh()->currencyBalances()['USD'])->toBe(1000);
});

test('correcting a confirmed plan payment marks the plan for review', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createPlanningObligation($profile, 'Correctable arrangement', '300.0000', '50.0000');

    Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    $transaction = app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '100.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-05',
        'external_reference' => null,
        'note' => 'Original payment',
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    app(UpdateTransaction::class)->handle($obligation, $transaction, [
        'status' => 'cancelled',
        'amount' => '100.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-05',
        'external_reference' => null,
        'note' => 'Payment cancelled',
        'entry_type' => 'payment',
        'balance_effect' => null,
    ]);

    expect(RepaymentPlan::query()->firstOrFail()->fresh()->status)->toBe('needs_review')
        ->and($obligation->fresh()->current_total_balance)->toBe(30000);
});

test('generating again supersedes the previous plan and keeps its history', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    createPlanningObligation($profile, 'Replanned arrangement', '300.0000', '50.0000');

    $component = Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    $firstPlan = RepaymentPlan::query()->firstOrFail();
    $component->call('generatePlan')->assertHasNoErrors();

    $plans = RepaymentPlan::query()->orderBy('created_at')->get();

    expect($plans)->toHaveCount(2)
        ->and($firstPlan->fresh()->status)->toBe('paused')
        ->and($firstPlan->fresh()->paused_at)->not->toBeNull()
        ->and($plans->last()->status)->toBe('active');
});

test('a paused plan becomes stale after a movement and refreshing it requires confirmation', function () {
    $user = User::factory()->create();
    $profile = FinancialProfile::query()->where('owner_user_id', $user->id)->firstOrFail();
    $obligation = createPlanningObligation($profile, 'Switchable arrangement', '300.0000', '50.0000');

    $component = Livewire::actingAs($user)->test(Index::class)
        ->set('startsOn', '2026-09-01')
        ->set('endsOn', '2026-09-30')
        ->call('createBudget')
        ->set('cashFlowAmount', '1000.0000')
        ->set('cashFlowName', 'Salary')
        ->set('cashFlowCategory', 'salary')
        ->call('addCashFlow')
        ->call('generatePlan')
        ->assertHasNoErrors();

    $firstPlan = RepaymentPlan::query()->firstOrFail();
    $component->call('generatePlan')->assertHasNoErrors();
    $secondPlan = RepaymentPlan::query()->latest('created_at')->orderByDesc('id')->firstOrFail();

    expect($firstPlan->fresh()->status)->toBe('paused')
        ->and($secondPlan->fresh()->status)->toBe('active');

    app(RecordTransaction::class)->handle($obligation, [
        'status' => 'confirmed',
        'amount' => '25.0000',
        'currency' => 'MYR',
        'occurred_on' => '2026-09-05',
        'external_reference' => 'PLAN-B-PAYMENT',
        'note' => 'Payment made under the active second plan',
        'entry_type' => 'payment',
        'balance_effect' => null,
        'repayment_plan_allocation_id' => $secondPlan->allocations()->firstOrFail()->id,
    ]);

    expect($firstPlan->fresh()->status)->toBe('needs_review')
        ->and($secondPlan->fresh()->status)->toBe('active');

    $component = Livewire::actingAs($user)->test(Index::class)
        ->call('activatePlan', $firstPlan->id)
        ->assertSet('activationPlanId', $firstPlan->id)
        ->assertSee('Refresh this plan before activating?')
        ->call('refreshAndActivatePlan')
        ->assertHasNoErrors();

    $plans = RepaymentPlan::query()->latest('created_at')->orderByDesc('id')->get();
    $refreshedPlan = $plans->firstOrFail();

    expect($plans)->toHaveCount(3)
        ->and($firstPlan->fresh()->status)->toBe('superseded')
        ->and($secondPlan->fresh()->status)->toBe('paused')
        ->and($refreshedPlan->status)->toBe('active')
        ->and($refreshedPlan->allocations()->firstOrFail()->carried_paid_amount)->toBe(2500);
    $component
        ->assertSet('activationPlanId', null)
        ->assertSet('activationPreview', []);

    $component
        ->call('pausePlan', $refreshedPlan->id)
        ->call('activatePlan', $secondPlan->id)
        ->assertHasNoErrors();

    expect($refreshedPlan->fresh()->status)->toBe('paused')
        ->and($secondPlan->fresh()->status)->toBe('active');
});

function createPlanningObligation(FinancialProfile $profile, string $title, string $balance, string $minimum): Obligation
{
    return $profile->records()->create(['title' => $title.' arrangement'])->obligations()->create([
        'direction' => 'payable',
        'obligation_kind' => 'money',
        'category' => 'personal_loan',
        'title' => $title,
        'status' => 'active',
        'currency' => $profile->base_currency,
        'current_principal_balance' => MoneyAmount::fromMajorOrZero($balance, $profile->base_currency),
        'current_total_balance' => MoneyAmount::fromMajorOrZero($balance, $profile->base_currency),
        'minimum_payment_amount' => MoneyAmount::fromMajorOrZero($minimum, $profile->base_currency),
        'data_confidence' => 'verified',
    ]);
}
