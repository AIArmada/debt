<?php

namespace App\Domain\Calculations;

class SimpleInterestCalculator
{
    public function monthlyCharge(int $principal, string $annualRate): int
    {
        $rate = $this->precise($annualRate);
        $monthlyCharge = bcdiv(bcdiv(bcmul((string) $principal, $rate, 12), '100', 12), '12', 12);

        return $this->roundMoney($this->precise($monthlyCharge));
    }

    /** @param numeric-string $value */
    private function roundMoney(string $value): int
    {
        if (bccomp($value, '0', 12) < 0) {
            return (int) bcsub($value, '0.5', 0);
        }

        return (int) bcadd($value, '0.5', 0);
    }

    /** @return numeric-string */
    private function precise(string $value): string
    {
        if (! is_numeric($value)) {
            throw new \UnexpectedValueException('The calculation produced a non-numeric result.');
        }

        return $value;
    }
}
