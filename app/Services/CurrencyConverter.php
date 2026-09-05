<?php

namespace App\Services;

use App\Domain\Money\Decimal;
use App\Models\FinancialProfile;

class CurrencyConverter
{
    /** @return numeric-string|null */
    public function convert(FinancialProfile $profile, string $amount, string $from, string $to): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return Decimal::normalise($amount);
        }

        $direct = $profile->exchangeRates()->where('from_currency', $from)->where('to_currency', $to)->latest('effective_on')->first();

        if ($direct !== null) {
            return Decimal::multiply(Decimal::normalise($amount), Decimal::normalise((string) $direct->rate));
        }

        $inverse = $profile->exchangeRates()->where('from_currency', $to)->where('to_currency', $from)->latest('effective_on')->first();

        if ($inverse !== null && bccomp((string) $inverse->rate, '0', 10) === 1) {
            return Decimal::divide(Decimal::normalise($amount), Decimal::normalise((string) $inverse->rate));
        }

        return null;
    }
}
