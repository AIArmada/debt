<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Models\Obligation;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

final class PromiseShowData
{
    /** @return EloquentCollection<int, Obligation> */
    public function obligations(Record $record): EloquentCollection
    {
        return $record->obligations()
            ->with(['moneySubject', 'quantitySubject', 'commitmentSubject', 'moneyMovements.recorder', 'moneyMovements.attachments', 'quantityReturns.recorder', 'quantityReturns.attachments', 'reminders'])
            ->get();
    }

    /** @return array<string, string> */
    public function restartQuery(Record $record): array
    {
        $obligation = $record->obligations()
            ->with(['quantitySubject', 'commitmentSubject', 'moneySubject'])
            ->oldest('created_at')
            ->oldest('id')
            ->firstOrFail();
        $party = $record->counterparties()->first();
        $query = [
            'partyName' => $party === null ? $record->title : $party->display_name,
            'direction' => $obligation->direction->value,
            'subjectType' => $obligation->subject_type->value,
            'dueOn' => $obligation->due_on?->toDateString() ?? '',
            'note' => (string) ($record->note ?? ''),
        ];

        if ($obligation->moneySubject !== null) {
            $query['amount'] = '';
        }

        if ($obligation->quantitySubject !== null) {
            $query += [
                'quantityName' => $obligation->quantitySubject->name,
                'quantityTotal' => (string) $obligation->quantitySubject->total,
                'quantityUnit' => $obligation->quantitySubject->unit,
                'isFractionable' => $obligation->quantitySubject->is_fractionable ? '1' : '0',
            ];
        }

        if ($obligation->commitmentSubject !== null) {
            $query['doneCriteria'] = $obligation->commitmentSubject->done_criteria;
        }

        return $query;
    }

    public function evidenceParent(Record $record, ?string $movementId, ?string $returnId): Model
    {
        if ($movementId !== null) {
            return $record->obligations()
                ->whereHas('moneyMovements', fn (Builder $query): Builder => $query->whereKey($movementId))
                ->with('moneyMovements')
                ->get()
                ->flatMap(static fn (Obligation $obligation): EloquentCollection => $obligation->moneyMovements)
                ->where('id', $movementId)
                ->firstOrFail();
        }

        if ($returnId !== null) {
            return $record->obligations()
                ->whereHas('quantityReturns', fn (Builder $query): Builder => $query->whereKey($returnId))
                ->with('quantityReturns')
                ->get()
                ->flatMap(static fn (Obligation $obligation): EloquentCollection => $obligation->quantityReturns)
                ->where('id', $returnId)
                ->firstOrFail();
        }

        return $record;
    }

    /**
     * @param  array<string, int>  $balances
     * @return array<int, array{currency: string, amount: int, direction: Direction|null, label: string}>
     */
    public function positions(Obligation $obligation, array $balances): array
    {
        return collect($balances)
            ->map(function (int $balance, string $currency) use ($obligation): array {
                $direction = $obligation->direction->forBalance($balance);

                return [
                    'currency' => $currency,
                    'amount' => abs($balance),
                    'direction' => $balance === 0 ? null : $direction,
                    'label' => $balance === 0 ? 'Settled' : $direction->label(),
                ];
            })
            ->values()
            ->all();
    }
}
