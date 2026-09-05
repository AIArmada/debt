<?php

namespace App\Domain\Calculations;

use InvalidArgumentException;

class PawnStorageCalculator
{
    public function feeForPeriods(int $feePerPeriod, int $periods): int
    {
        $periods = max(0, $periods);

        if ($feePerPeriod !== 0 && abs($periods) > intdiv(PHP_INT_MAX, abs($feePerPeriod))) {
            throw new InvalidArgumentException('The projected storage fee is too large.');
        }

        return $feePerPeriod * $periods;
    }
}
