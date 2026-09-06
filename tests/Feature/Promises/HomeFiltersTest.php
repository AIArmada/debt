<?php

use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\RecordArchiveFilter;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\DueDateQuery;
use App\Livewire\Promises\Index as PromisesIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
