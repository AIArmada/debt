<?php

use App\Actions\Promises\ConfirmProposedMovement;
use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\GenerateInstalmentSchedule;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\RegenerateInstalments;
use App\Actions\Promises\SettleObligation;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\Direction;
use App\Domain\Enums\InstalmentStatus;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Queries\DueDateQuery;
use App\Models\Instalment;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

pest()->use(RefreshDatabase::class);

/** @return array{0: Record, 1: Obligation} */
function instalmentPromise(User $user, string $amount = '100.00', ?string $dueOn = null): array
{
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Instalment counterparty',
        'direction' => Direction::Payable->value,
        'amount' => $amount,
        'dueOn' => $dueOn,
    ], 'MYR'));

    return [$record, $record->obligations->firstOrFail()];
}

test('monthly instalments clamp January 31 to February and April month ends', function () {
    $this->travelTo(Carbon::parse('2026-01-20'));
    $user = User::factory()->create();
    [$record, $obligation] = instalmentPromise($user, '100.01');

    $instalments = app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
        'count' => 4,
        'frequency' => 'monthly',
        'firstDueOn' => '2026-01-31',
        'payDayOfMonth' => '',
    ]));

    expect(array_map(static fn (Instalment $instalment): int => $instalment->amount_minor, $instalments))->toBe([2500, 2500, 2500, 2501])
        ->and(array_map(static fn (Instalment $instalment): string => $instalment->due_on->toDateString(), $instalments))->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'])
        ->and($record->obligations()->firstOrFail()->instalments()->count())->toBe(4);
});

test('monthly pay-day schedules start at the next pay day in the profile timezone', function () {
    $this->travelTo(Carbon::parse('2026-05-20 16:30:00', 'UTC'));
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $profile->forceFill(['timezone' => 'Asia/Kuala_Lumpur'])->save();
    [, $obligation] = instalmentPromise($user, '100.00');

    $instalments = app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
        'count' => 2,
        'frequency' => 'monthly',
        'firstDueOn' => '',
        'payDayOfMonth' => 20,
    ]));

    expect(array_map(static fn (Instalment $instalment): string => $instalment->due_on->toDateString(), $instalments))
        ->toBe(['2026-06-20', '2026-07-20']);
});

test('a posted fixed-total profit is included in a schedule exactly once', function () {
    $user = User::factory()->create();
    [, $obligation] = instalmentPromise($user, '100.00');
    ObligationChargeTerm::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'mode' => 'manual',
        'kind' => 'profit',
        'basis' => 'fixed_total',
        'total_payable_minor' => 12000,
        'fixed_total_posted_at' => now(),
    ]);
    MoneyMovement::factory()->create([
        'obligation_id' => $obligation->getKey(),
        'entry' => MoneyEntry::ProfitCharge,
        'amount_minor' => 2000,
        'currency' => 'MYR',
        'calculation' => ['fixed_total' => true],
        'recorded_by' => $user->getKey(),
    ]);

    $instalments = app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
        'count' => 4,
        'frequency' => 'weekly',
        'firstDueOn' => today()->addWeek()->toDateString(),
        'payDayOfMonth' => '',
    ]));

    expect(array_sum(array_map(static fn (Instalment $instalment): int => $instalment->amount_minor, $instalments)))->toBe(12000);
});

test('confirmed partial and full settlements allocate oldest instalments and void reverses allocations exactly', function () {
    $user = User::factory()->create();
    [$record, $obligation] = instalmentPromise($user, '100.00');
    app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
        'count' => 2,
        'frequency' => 'weekly',
        'firstDueOn' => today()->addWeek()->toDateString(),
        'payDayOfMonth' => '',
    ]));

    $firstPayment = app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::settlement(2500, 'MYR', today()->toDateString()));
    $secondPayment = app(RecordMoneyMovement::class)->handle($user, $obligation, RecordMovementData::settlement(3500, 'MYR', today()->toDateString()));

    expect($obligation->instalments()->orderBy('sequence')->pluck('paid_minor')->all())->toBe([5000, 1000])
        ->and((int) $secondPayment->instalmentAllocations()->sum('amount_minor'))->toBe(3500)
        ->and($obligation->instalments()->orderBy('sequence')->firstOrFail()->status())->toBe(InstalmentStatus::Settled);

    app(VoidMovement::class)->handle($user, $secondPayment, VoidMovementData::fromInput(['reason' => 'Wrong amount']));
    expect($obligation->instalments()->orderBy('sequence')->pluck('paid_minor')->all())->toBe([2500, 0])
        ->and($secondPayment->instalmentAllocations()->count())->toBe(0);

    app(SettleObligation::class)->handle($user, $obligation->fresh(), SettleObligationData::fromInput([]));
    expect($obligation->instalments()->orderBy('sequence')->pluck('paid_minor')->all())->toBe([5000, 5000])
        ->and($obligation->instalments()->get()->every(fn (Instalment $instalment): bool => $instalment->status() === InstalmentStatus::Settled))->toBeTrue();
});

test('a confirmed proposed settlement allocates only when confirmed and regeneration refuses allocated payments', function () {
    $user = User::factory()->create();
    [$record, $obligation] = instalmentPromise($user, '20.00');
    app(GenerateInstalmentSchedule::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
        'count' => 2,
        'frequency' => 'weekly',
        'firstDueOn' => today()->addWeek()->toDateString(),
        'payDayOfMonth' => '',
    ]));
    $proposal = MoneyMovement::factory()->proposed()->create([
        'obligation_id' => $obligation->getKey(),
        'entry' => MoneyEntry::Payment,
        'amount_minor' => 500,
        'currency' => 'MYR',
        'recorded_by' => $user->getKey(),
    ]);

    expect((int) $obligation->instalments()->sum('paid_minor'))->toBe(0);
    app(ConfirmProposedMovement::class)->handle($user, $proposal);
    expect((int) $obligation->instalments()->sum('paid_minor'))->toBe(500)
        ->and(fn () => app(RegenerateInstalments::class)->handle($user, $obligation, GenerateInstalmentScheduleData::fromInput([
            'count' => 2,
            'frequency' => 'weekly',
            'firstDueOn' => today()->addWeeks(2)->toDateString(),
            'payDayOfMonth' => '',
        ])))->toThrow(ValidationException::class);
});

test('due queries use the next unpaid instalment instead of final maturity', function () {
    $user = User::factory()->create();
    [$record, $obligation] = instalmentPromise($user, '30.00', today()->subDays(30)->toDateString());
    $obligation->instalments()->create([
        'sequence' => 1,
        'due_on' => today()->addDays(3),
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'paid_minor' => 0,
    ]);
    $dueDateQuery = app(DueDateQuery::class);

    expect($dueDateQuery->recordIsOverdue($record))->toBeFalse()
        ->and($dueDateQuery->dueSoon(Record::query()->whereKey($record->getKey()))->exists())->toBeTrue();

    $obligation->instalments()->firstOrFail()->forceFill(['due_on' => today()->subDay()])->save();
    expect($dueDateQuery->recordIsOverdue($record->fresh()))->toBeTrue();
});
