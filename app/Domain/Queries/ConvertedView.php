<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Money\Currency;
use App\Domain\StringNormalizer;
use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class ConvertedView
{
    public function __construct(private readonly MoneyBalanceQuery $moneyBalanceQuery) {}

    /**
     * @return array{target_currency: string, to_pay: array<string, array{amount_minor: int, rate: string, rated_on: string, stale: bool}>, to_receive: array<string, array{amount_minor: int, rate: string, rated_on: string, stale: bool}>}
     */
    public function forProfile(FinancialProfile $profile, string $target, ?string $rateId = null): array
    {
        $target = StringNormalizer::uppercase($target);
        if (! Currency::isSupported($target)) {
            throw ValidationException::withMessages(['target' => 'Choose a supported target currency.']);
        }

        $converted = ['target_currency' => $target, 'to_pay' => [], 'to_receive' => []];
        foreach ($this->moneyBalanceQuery->forProfile($profile) as $row) {
            $balance = (int) $row->balance;
            if ($balance === 0) {
                continue;
            }
            $bucket = Direction::from((string) $row->direction)->bucketForBalance($balance);
            $rate = $this->rate((string) $row->currency, $target, $rateId);
            if ($rate === null && StringNormalizer::uppercase((string) $row->currency) !== $target) {
                throw ValidationException::withMessages(['rate' => "No saved rate exists for {$row->currency} to {$target}."]);
            }
            $rateValue = '1.00000000';
            $ratedOn = today()->toDateString();
            $stale = false;
            if ($rate !== null) {
                $rateValue = $rate->rate;
                $ratedOn = $rate->rated_on->toDateString();
                $stale = $rate->rated_on->lt(today()->subDays(30));
            }
            $amount = $this->convert(abs($balance), $rateValue);
            $key = $target;
            $converted[$bucket][$key] = [
                'amount_minor' => ($converted[$bucket][$key]['amount_minor'] ?? 0) + $amount,
                'rate' => $rateValue,
                'rated_on' => $ratedOn,
                'stale' => $stale,
            ];
        }

        return $converted;
    }

    private function rate(string $from, string $to, ?string $rateId): ?ExchangeRate
    {
        if (StringNormalizer::uppercase($from) === $to) {
            return null;
        }

        $query = ExchangeRate::query()->where('from_currency', StringNormalizer::uppercase($from))->where('to_currency', $to);
        if ($rateId !== null) {
            return $query->whereKey($rateId)->first();
        }

        return $query->latest('rated_on')->first();
    }

    private function convert(int $minor, string $rate): int
    {
        return BigDecimal::of($minor)
            ->multipliedBy($rate)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }
}
