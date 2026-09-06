<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\StringNormalizer;
use App\Models\FinancialProfile;

final class ProfileTotals
{
    public function __construct(private readonly MoneyBalanceQuery $moneyBalanceQuery) {}

    /** @return array{to_pay: array<string, int>, to_receive: array<string, int>} */
    public function forProfile(FinancialProfile $profile): array
    {
        $totals = ['to_pay' => [], 'to_receive' => []];

        foreach ($this->moneyBalanceQuery->forProfile($profile) as $row) {
            $balance = (int) $row->balance;
            if ($balance === 0) {
                continue;
            }
            $bucket = Direction::from((string) $row->direction)->bucketForBalance($balance);
            $currency = StringNormalizer::uppercase((string) $row->currency);
            $totals[$bucket][$currency] = ($totals[$bucket][$currency] ?? 0) + abs($balance);
        }

        return $totals;
    }
}
