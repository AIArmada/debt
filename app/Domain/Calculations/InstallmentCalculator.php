<?php

namespace App\Domain\Calculations;

class InstallmentCalculator
{
    public function projectedTotal(int $installmentAmount, int $numberOfInstallments): int
    {
        return $installmentAmount * max(0, $numberOfInstallments);
    }
}
