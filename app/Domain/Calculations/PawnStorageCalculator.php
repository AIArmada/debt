<?php

namespace App\Domain\Calculations;

class PawnStorageCalculator
{
    public function feeForPeriods(int $feePerPeriod, int $periods): int
    {
        return $feePerPeriod * max(0, $periods);
    }
}
