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

        return $statuses->isNotEmpty() && $statuses->every(
            static fn (ObligationStatus $status): bool => $status === ObligationStatus::Settled,
        ) ? ObligationStatus::Settled : ObligationStatus::Open;
    }
}
