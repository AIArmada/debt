<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\StringNormalizer;
use App\Models\ImportRow;
use App\Models\Obligation;
use App\Models\Record;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class ImportMatcher
{
    public function __construct(private readonly OutstandingBalance $outstandingBalance) {}

    public function suggest(ImportRow $row): ?Obligation
    {
        $row->loadMissing('batch.profile');
        $obligations = $row->batch->profile->records()
            ->where('is_archived', false)
            ->with(['obligations.moneySubject', 'obligations.record.partyLinks.party'])
            ->get()
            ->flatMap(static fn (Record $record): EloquentCollection => $record->obligations)
            ->filter(fn (Obligation $obligation): bool => $obligation->subject_type === SubjectType::Money
                && $obligation->status === ObligationStatus::Open
                && $obligation->moneySubject?->currency === $row->currency
                && ($this->outstandingBalance->forObligation($obligation)[$row->currency] ?? 0) >= $row->amount_minor)
            ->sortBy(function (Obligation $obligation) use ($row): array {
                $dateDistance = abs($obligation->due_on?->diffInDays($row->occurred_on) ?? 3650);
                $description = StringNormalizer::lowercase((string) $row->description);
                $nameMatch = $description !== '' && str_contains(StringNormalizer::lowercase((string) $obligation->title), $description) ? 0 : 1;

                return [$nameMatch, $dateDistance, $obligation->getKey()];
            })
            ->first();

        if (! $obligations instanceof Obligation) {
            return null;
        }

        $row->forceFill([
            'suggested_obligation_id' => $obligations->getKey(),
            'suggested_entry' => ($obligations->direction === Direction::Payable ? MoneyEntry::Payment : MoneyEntry::Collection)->value,
        ])->save();

        return $obligations;
    }
}
