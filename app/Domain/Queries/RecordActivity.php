<?php

namespace App\Domain\Queries;

use App\Models\ActivityEntry;
use App\Models\Attachment;
use App\Models\CommitmentSubject;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class RecordActivity
{
    /** @return EloquentCollection<int, ActivityEntry> */
    public function forRecord(Record $record): EloquentCollection
    {
        $record->loadMissing([
            'obligations.commitmentSubject',
            'obligations.moneyMovements',
            'obligations.quantityReturns',
            'obligations.reminders',
            'obligations.attachments',
            'attachments',
        ]);

        $obligations = $record->obligations;
        $moneyMovements = $obligations->flatMap(static fn (Obligation $obligation) => $obligation->moneyMovements);
        $quantityReturns = $obligations->flatMap(static fn (Obligation $obligation) => $obligation->quantityReturns);
        $reminders = $obligations->flatMap(static fn (Obligation $obligation) => $obligation->reminders);
        $attachmentIds = $this->attachmentIds($record, $obligations, $moneyMovements, $quantityReturns);
        $subjects = [
            (new Record)->getMorphClass() => [$record->getKey()],
            (new Obligation)->getMorphClass() => $obligations->modelKeys(),
            (new CommitmentSubject)->getMorphClass() => $obligations->pluck('commitmentSubject.obligation_id')->filter()->values()->all(),
            (new MoneyMovement)->getMorphClass() => $moneyMovements->map(static fn (MoneyMovement $movement): string => $movement->getKey())->all(),
            (new QuantityReturn)->getMorphClass() => $quantityReturns->map(static fn (QuantityReturn $return): string => $return->getKey())->all(),
            (new Reminder)->getMorphClass() => $reminders->map(static fn (Reminder $reminder): string => $reminder->getKey())->all(),
            (new Attachment)->getMorphClass() => $attachmentIds,
        ];

        return ActivityEntry::query()
            ->where('profile_id', $record->profile_id)
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as $subjectType => $subjectIds) {
                    if ($subjectIds === []) {
                        continue;
                    }

                    $query->orWhere(function (Builder $subjectQuery) use ($subjectType, $subjectIds): void {
                        $subjectQuery
                            ->where('subject_type', $subjectType)
                            ->whereIn('subject_id', $subjectIds);
                    });
                }
            })
            ->with('actor')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Include soft-deleted attachments so the detach event remains visible.
     *
     * @param  EloquentCollection<int, Obligation>  $obligations
     * @param  Collection<int, MoneyMovement>  $moneyMovements
     * @param  Collection<int, QuantityReturn>  $quantityReturns
     * @return list<string>
     */
    private function attachmentIds(Record $record, EloquentCollection $obligations, Collection $moneyMovements, Collection $quantityReturns): array
    {
        $parents = [
            (new Record)->getMorphClass() => [$record->getKey()],
            (new Obligation)->getMorphClass() => $obligations->modelKeys(),
            (new MoneyMovement)->getMorphClass() => $moneyMovements->map(static fn (MoneyMovement $movement): string => $movement->getKey())->all(),
            (new QuantityReturn)->getMorphClass() => $quantityReturns->map(static fn (QuantityReturn $return): string => $return->getKey())->all(),
        ];

        return array_values(Attachment::withTrashed()
            ->where('profile_id', $record->profile_id)
            ->where(function (Builder $query) use ($parents): void {
                foreach ($parents as $parentType => $parentIds) {
                    if ($parentIds === []) {
                        continue;
                    }

                    $query->orWhere(function (Builder $parentQuery) use ($parentType, $parentIds): void {
                        $parentQuery
                            ->where('attachable_type', $parentType)
                            ->whereIn('attachable_id', $parentIds);
                    });
                }
            })
            ->pluck('id')
            ->values()
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all());
    }
}
