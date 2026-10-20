<?php

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\RecordArchiveFilter;
use App\Domain\Enums\SubjectType;
use App\Domain\Money\Money;
use App\Domain\Queries\DueDateQuery;
use App\Livewire\Promises\Index as PromisesIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

test('home filters use one partition for overdue and due soon records', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $profile = $user->financialProfiles()->firstOrFail();
    $makeRecord = function (string $title, ?string $dueOn, ObligationStatus $status = ObligationStatus::Open, bool $archived = false) use ($profile) {
        $record = $profile->records()->create([
            'title' => $title,
            'note' => null,
            'is_archived' => $archived,
        ]);
        $obligation = $record->obligations()->create([
            'direction' => Direction::Payable,
            'title' => $title,
            'status' => $status,
            'subject_type' => SubjectType::Commitment,
            'due_on' => $dueOn,
        ]);
        $obligation->commitmentSubject()->create([
            'done_criteria' => 'Finish the promise.',
            'completed_at' => $status === ObligationStatus::Settled ? now() : null,
        ]);

        return $record;
    };

    $open = $makeRecord('Open promise', null);
    $settled = $makeRecord('Settled promise', null, ObligationStatus::Settled);
    $overdue = $makeRecord('Overdue promise', today()->subDay()->toDateString());
    $dueSoon = $makeRecord('Due soon promise', today()->addDay()->toDateString());
    $archived = $makeRecord('Archived promise', null, ObligationStatus::Open, true);

    $dueDateQuery = app(DueDateQuery::class);
    $overdueIds = $dueDateQuery->overdue($profile->records()->getQuery())->pluck('records.id');
    $dueSoonIds = $dueDateQuery->dueSoon($profile->records()->getQuery())->pluck('records.id');

    expect($overdueIds->all())->toBe([$overdue->getKey()])
        ->and($dueSoonIds->all())->toBe([$dueSoon->getKey()])
        ->and($overdueIds->intersect($dueSoonIds))->toBeEmpty();

    $component = new PromisesIndex;
    $component->mount($profile);
    $viewData = app()->call([$component, 'render'])->getData();

    expect($viewData['filterCounts']->all())->toMatchArray([
        RecordArchiveFilter::Active->value => 4,
        RecordArchiveFilter::Archived->value => 1,
        RecordArchiveFilter::Open->value => 3,
        RecordArchiveFilter::Settled->value => 1,
        RecordArchiveFilter::DueSoon->value => 1,
        RecordArchiveFilter::Overdue->value => 1,
        RecordArchiveFilter::All->value => 5,
    ]);

    $sortedRecordIds = $viewData['records']->pluck('id')->all();
    expect($sortedRecordIds[0])->toBe($overdue->getKey())
        ->and(array_values(array_intersect($sortedRecordIds, [
            $open->getKey(),
            $dueSoon->getKey(),
            $settled->getKey(),
        ])))->toHaveCount(3);

    foreach ([
        RecordArchiveFilter::Open->value => [$open, $overdue, $dueSoon],
        RecordArchiveFilter::Settled->value => [$settled],
        RecordArchiveFilter::DueSoon->value => [$dueSoon],
        RecordArchiveFilter::Overdue->value => [$overdue],
        RecordArchiveFilter::Archived->value => [$archived],
    ] as $filter => $expectedRecords) {
        $component->statusFilter = $filter;
        $records = app()->call([$component, 'render'])->getData()['records'];

        $recordIds = $records->pluck('id')->all();
        $expectedIds = array_map(
            static fn ($record): string => $record->getKey(),
            $expectedRecords,
        );

        expect(array_diff($recordIds, $expectedIds))->toBe([])
            ->and(array_diff($expectedIds, $recordIds))->toBe([]);

        if (in_array($overdue->getKey(), $expectedIds, true)) {
            expect($recordIds[0])->toBe($overdue->getKey());
        }
    }
});

test('promise filters wrap instead of extending the row beyond a narrow viewport', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();

    $component = Livewire::actingAs($user)->test(PromisesIndex::class, ['profile' => $profile]);

    expect($component->html())->toContain('flex flex-wrap gap-3 text-sm');
});

test('money promise rows render borrowed charges and paid breakdown amounts', function () {
    $user = User::factory()->create();
    $profile = $user->financialProfiles()->firstOrFail();
    $record = app(CreatePromise::class)->handle($user, $profile, CreatePromiseData::fromInput([
        'partyName' => 'Breakdown promise',
        'direction' => Direction::Payable->value,
        'amount' => '100.00',
    ], 'MYR'));
    $obligation = $record->obligations->firstOrFail();
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Fee,
        'amount_minor' => 1000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'recorded_by' => $user->getKey(),
    ]);
    $obligation->moneyMovements()->create([
        'entry' => MoneyEntry::Payment,
        'amount_minor' => 2000,
        'currency' => 'MYR',
        'occurred_on' => today(),
        'status' => MovementStatus::Confirmed,
        'recorded_by' => $user->getKey(),
    ]);

    $page = Livewire::actingAs($user)->test(PromisesIndex::class, ['profile' => $profile]);

    $page->assertSee(__('promises.breakdown.borrowed'))
        ->assertSee(__('promises.breakdown.charges'))
        ->assertSee(__('promises.breakdown.paid'))
        ->assertSee(Money::display(10000, 'MYR'))
        ->assertSee(Money::display(1000, 'MYR'))
        ->assertSee(Money::display(2000, 'MYR'));
});
