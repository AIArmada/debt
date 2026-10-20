<?php

use App\Actions\Promises\CreateBudgetPeriod;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreateBudgetPeriodData;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\GeneratePlan;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\PlanStrategy;
use App\Domain\Queries\DebtFreeProjection;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

/** @return array{0: Record, 1: Obligation} */
function planPromise(User $user, string $party, string $amount, string $dueOn): array
{
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => $party,
        'direction' => Direction::Payable->value,
        'amount' => $amount,
        'dueOn' => $dueOn,
    ], 'MYR'));

    return [$record, $record->obligations->firstOrFail()];
}

function planBudget(User $user, string $income, int $offsetDays = 0, string $essentials = '0', string $reserve = '0'): BudgetPeriod
{
    $profile = $user->financialProfiles()->firstOrFail();

    return app(CreateBudgetPeriod::class)->handle($user, $profile, CreateBudgetPeriodData::fromInput([
        'startsOn' => today()->addDays($offsetDays)->toDateString(),
        'endsOn' => today()->addDays($offsetDays + 30)->toDateString(),
        'income' => $income,
        'essentials' => $essentials,
        'reserve' => $reserve,
        'currency' => 'MYR',
    ]));
}

test('budget values parse as major units and reject unsupported precision', function () {
    $user = User::factory()->create();
    $period = planBudget($user, '3500');

    expect($period->income_minor)->toBe(350000);
    expect(fn () => CreateBudgetPeriodData::fromInput([
        'startsOn' => today()->toDateString(),
        'endsOn' => today()->addDays(30)->toDateString(),
        'income' => '1.001',
        'essentials' => '0',
        'reserve' => '0',
        'currency' => 'MYR',
    ]))->toThrow(ValidationException::class);
});

test('due date, snowball, and avalanche strategies order allocations as specified', function () {
    $user = User::factory()->create();
    [, $dueSoon] = planPromise($user, 'Due soon', '100.00', today()->addDay()->toDateString());
    [, $small] = planPromise($user, 'Small', '20.00', today()->addDays(5)->toDateString());
    [, $daily] = planPromise($user, 'Daily rate', '80.00', today()->addDays(10)->toDateString());
    [, $yearly] = planPromise($user, 'Yearly rate', '80.00', today()->addDays(2)->toDateString());
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $daily->getKey(),
        'kind' => 'interest',
        'basis' => 'reducing_simple',
        'period' => 'daily',
        'rate' => '0.00100000',
    ]);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $yearly->getKey(),
        'kind' => 'interest',
        'basis' => 'reducing_simple',
        'period' => 'yearly',
        'rate' => '0.10000000',
    ]);

    $dueDatePlan = app(GeneratePlan::class)->handle($user, planBudget($user, '50.00'), PlanStrategy::DueDate);
    $snowballPlan = app(GeneratePlan::class)->handle($user, planBudget($user, '20.00', 31), PlanStrategy::Snowball);
    $avalanchePlan = app(GeneratePlan::class)->handle($user, planBudget($user, '50.00', 62), PlanStrategy::Avalanche);

    expect($dueDatePlan->allocations->firstOrFail()->obligation_id)->toBe($dueSoon->getKey())
        ->and($snowballPlan->allocations->firstOrFail()->obligation_id)->toBe($small->getKey())
        ->and($avalanchePlan->allocations->firstOrFail()->obligation_id)->toBe($daily->getKey())
        ->and($avalanchePlan->strategy)->toBe(PlanStrategy::Avalanche);
});

test('instalments cap a period allocation at arrears plus instalments due in the period', function () {
    $user = User::factory()->create();
    [, $obligation] = planPromise($user, 'Scheduled debt', '100.00', today()->addDays(90)->toDateString());
    $obligation->instalments()->createMany([
        ['sequence' => 1, 'due_on' => today()->subDay(), 'amount_minor' => 2000, 'currency' => 'MYR', 'paid_minor' => 0],
        ['sequence' => 2, 'due_on' => today()->addDays(5), 'amount_minor' => 1500, 'currency' => 'MYR', 'paid_minor' => 0],
        ['sequence' => 3, 'due_on' => today()->addDays(45), 'amount_minor' => 6500, 'currency' => 'MYR', 'paid_minor' => 0],
    ]);

    $plan = app(GeneratePlan::class)->handle($user, planBudget($user, '100.00'));

    expect($plan->allocations->firstOrFail()->planned_minor)->toBe(3500);
});

test('debt-free projection is deterministic and estimates monthly reducing charges', function () {
    $user = User::factory()->create();
    [, $obligation] = planPromise($user, 'Projection debt', '120.00', today()->addDay()->toDateString());
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'kind' => 'interest',
        'basis' => 'reducing_simple',
        'period' => 'monthly',
        'rate' => '0.01000000',
    ]);
    $period = planBudget($user, '60.00');

    $projection = app(DebtFreeProjection::class)->forPeriod($user, $period, PlanStrategy::DueDate);

    expect($projection['months_to_free'])->toBe(3)
        ->and($projection['total_estimated_charges_minor'])->toBe(183)
        ->and($projection['extra_months_to_free'])->toBe(2)
        ->and($projection['months_earlier'])->toBe(1)
        ->and($projection['charges_saved_minor'])->toBe(8);
});

test('debt-free projection uses repayment capacity after essentials and reserve', function () {
    $user = User::factory()->create();
    planPromise($user, 'Capacity check', '50.00', today()->addDay()->toDateString());
    $period = planBudget($user, '100.00', 0, '70.00', '10.00');

    $projection = app(DebtFreeProjection::class)->forPeriod($user, $period, PlanStrategy::DueDate);

    expect($period->capacity())->toBe(2000)
        ->and($projection['months_to_free'])->toBe(3)
        ->and($projection['extra_capacity_minor'])->toBe(2200);
});

test('an authenticated user can open a budget period through the plans route', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $period = planBudget($user, '3500.00');

    $this->actingAs($user)
        ->get(route('plans.show', ['profile' => $profile, 'budgetPeriod' => $period]))
        ->assertOk()
        ->assertSee('Repayment plan');
});

test('the plans route returns not found for a budget period from another profile', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $otherProfile = FinancialProfile::factory()->create();
    $otherPeriod = BudgetPeriod::factory()->create(['profile_id' => $otherProfile->getKey()]);

    $this->actingAs($user)
        ->get(route('plans.show', ['profile' => $profile, 'budgetPeriod' => $otherPeriod]))
        ->assertNotFound();
});

test('debt-free projection stops estimated charges at the lifetime cap', function () {
    $user = User::factory()->create();
    [, $obligation] = planPromise($user, 'Capped projection', '100.00', today()->addDay()->toDateString());
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01000000',
        'cap_minor' => 100,
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::InterestCharge,
        'amount_minor' => 50,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'recorded_by' => $user->getKey(),
    ]);

    $projection = app(DebtFreeProjection::class)->forPeriod($user, planBudget($user, '5.00'), PlanStrategy::DueDate);

    expect($projection['total_estimated_charges_minor'])->toBe(50);
});

test('debt-free projection adds no charge while each obligation is inside its grace period', function () {
    $user = User::factory()->create();
    [, $obligation] = planPromise($user, 'Grace projection', '20.00', today()->addDay()->toDateString());
    $obligation->moneyMovements()->where('entry', MoneyEntry::OpeningBalance->value)->update(['occurred_on' => today()->toDateString()]);
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'kind' => 'interest',
        'basis' => 'flat',
        'period' => 'monthly',
        'rate' => '0.01000000',
        'grace_days' => 60,
    ]);

    $projection = app(DebtFreeProjection::class)->forPeriod($user, planBudget($user, '20.00'), PlanStrategy::DueDate);

    expect($projection['months_to_free'])->toBe(1)
        ->and($projection['total_estimated_charges_minor'])->toBe(0);
});
