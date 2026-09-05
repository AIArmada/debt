<?php

namespace App\Domain\Calculations;

class SimpleInterestCalculator
{
    public function monthlyCharge(int $principal, string $annualRate): int
    {
        $monthlyCharge = bcdiv(bcdiv(bcmul((string) $principal, $annualRate, 12), '100', 12), '12', 12);

        return $this->roundMoney($monthlyCharge);
    }

    private function roundMoney(string $value): int
    {
        return (int) bcadd($value, '0.5', 0);
    }
}
