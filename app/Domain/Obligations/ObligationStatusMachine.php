<?php

namespace App\Domain\Obligations;

use App\Domain\Enums\ObligationStatus;

final class ObligationStatusMachine
{
    /** @param array<string, int> $balances */
    public function afterConfirmedMovement(ObligationStatus $current, array $balances): ObligationStatus
    {
        if ($this->isSettled($balances)) {
            return ObligationStatus::Settled;
        }

        return ObligationStatus::Open;
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
        return bccomp($remaining, '0', 4) <= 0
            ? ObligationStatus::Settled
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
