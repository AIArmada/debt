<?php

namespace App\Domain\Queries;

use App\Models\Obligation;
use Illuminate\Support\Collection;

final class OutstandingBalance
{
    /** @var array<string, array<string, int>> */
    private array $cache = [];

    public function __construct(private readonly MoneyBalanceQuery $moneyBalanceQuery) {}

    /** @return array<string, int> */
    public function forObligation(Obligation $obligation): array
    {
        $key = (string) $obligation->getKey();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->moneyBalanceQuery->forObligation($obligation);
    }

    public function forget(Obligation $obligation): void
    {
        unset($this->cache[(string) $obligation->getKey()]);
    }

    /**
     * @param  Collection<int, Obligation>  $obligations
     * @return array<string, array<string, int>>
     */
    public function forObligations(Collection $obligations): array
    {
        $balances = [];

        foreach ($obligations as $obligation) {
            $balances[$obligation->getKey()] = $this->forObligation($obligation);
        }

        return $balances;
    }
}
