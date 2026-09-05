<?php

namespace App\Domain\Calculations;

use InvalidArgumentException;

class InstallmentCalculator
{
    public function projectedTotal(int $installmentAmount, int $numberOfInstallments): int
    {
        $periods = max(0, $numberOfInstallments);

        if ($installmentAmount !== 0 && abs($periods) > intdiv(PHP_INT_MAX, abs($installmentAmount))) {
            throw new InvalidArgumentException('The projected total is too large.');
        }

        return $installmentAmount * $periods;
    }
}
