<?php

namespace App\Domain\Obligations;

use App\Domain\Enums\ClosureReason;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\ObligationStatus;

final class ObligationStatusMachine
{
    /** @param array<string, int> $balances */
    public function afterConfirmedMovement(ObligationStatus $current, array $balances): ObligationStatus
    {
        if ($this->isSettled($balances)) {
            return ObligationStatus::Settled;
        }

        if (in_array($current, [ObligationStatus::Paused, ObligationStatus::Disputed], true)) {
            return $current;
        }

        return ObligationStatus::Open;
    }

    public function closureReasonForMovement(MoneyEntry $entry): ?ClosureReason
    {
        return match ($entry) {
            MoneyEntry::Payment, MoneyEntry::Collection => ClosureReason::Paid,
            MoneyEntry::Forgiveness => ClosureReason::Forgiven,
            MoneyEntry::WriteOff => ClosureReason::WrittenOff,
            MoneyEntry::Offset => ClosureReason::Offset,
            default => null,
        };
    }

    /** @param array<string, int> $balances */
    public function isSettled(array $balances): bool
    {
        if ($balances === []) {
            return false;
        }

        foreach ($balances as $balance) {
            if ($balance !== 0) {
                return false;
            }
        }

        return true;
    }

    /** @param numeric-string $remaining */
    public function afterQuantityReturn(ObligationStatus $current, string $remaining): ObligationStatus
    {
        if (bccomp($remaining, '0', 4) <= 0) {
            return ObligationStatus::Settled;
        }

        return in_array($current, [ObligationStatus::Paused, ObligationStatus::Disputed], true)
            ? $current
            : ObligationStatus::Open;
    }

    public function afterCommitmentCompletion(): ObligationStatus
    {
        return ObligationStatus::Settled;
    }

    public function afterCommitmentReopened(): ObligationStatus
    {
        return ObligationStatus::Open;
    }
}
