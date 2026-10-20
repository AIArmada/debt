<?php

namespace App\Domain\Queries;

use App\Domain\Enums\ObligationStatus;
use App\Models\Record;

final class RecordStatusQuery
{
    public function forRecord(Record $record): ObligationStatus
    {
        $statuses = $record->obligations()
            ->pluck('status')
            ->map(static fn (mixed $status): ObligationStatus => $status instanceof ObligationStatus ? $status : ObligationStatus::from((string) $status));

        if ($statuses->isNotEmpty() && $statuses->every(
            static fn (ObligationStatus $status): bool => $status === ObligationStatus::Settled,
        )) {
            return ObligationStatus::Settled;
        }

        if ($statuses->contains(ObligationStatus::Disputed)) {
            return ObligationStatus::Disputed;
        }

        if ($statuses->contains(ObligationStatus::Paused)) {
            return ObligationStatus::Paused;
        }

        return ObligationStatus::Open;
    }
}
