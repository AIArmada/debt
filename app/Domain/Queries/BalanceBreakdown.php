<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class BalanceBreakdown
{
    /**
     * @return array<string, array{
     *     principal_in: int,
     *     charges_in: int,
     *     settled_total: int,
     *     unpaid_charges: int,
     *     unpaid_principal: int
     * }>
     */
    public function forObligation(Obligation $obligation): array
    {
        return $this->forObligations(new EloquentCollection([$obligation]))[(string) $obligation->getKey()] ?? [];
    }

    /**
     * @param  Collection<int, Obligation>  $obligations
     * @return array<string, array<string, array{
     *     principal_in: int,
     *     charges_in: int,
     *     settled_total: int,
     *     unpaid_charges: int,
     *     unpaid_principal: int
     * }>>
     */
    public function forObligations(Collection $obligations): array
    {
        if ($obligations->isEmpty()) {
            return [];
        }

        $movementsByObligation = MoneyMovement::query()
            ->whereIn('obligation_id', $obligations->map(static fn (Obligation $obligation): string => (string) $obligation->getKey())->all())
            ->where('status', MovementStatus::Confirmed->value)
            ->get(['obligation_id', 'entry', 'amount_minor', 'currency'])
            ->groupBy('obligation_id');
        $breakdowns = [];

        foreach ($obligations as $obligation) {
            $terms = $obligation->chargeTerms;
            $allocationOrder = $terms instanceof ObligationChargeTerm ? $terms->allocation_order : 'charges_first';
            $movementsByCurrency = $movementsByObligation->get((string) $obligation->getKey(), collect())->groupBy('currency');
            $breakdowns[(string) $obligation->getKey()] = [];

            foreach ($movementsByCurrency as $currency => $currencyMovements) {
                $principalIn = 0;
                $chargesIn = 0;
                $settledTotal = 0;

                foreach ($currencyMovements as $movement) {
                    $amountMinor = (int) $movement->amount_minor;
                    $entry = $movement->entry;

                    if (in_array($entry, [MoneyEntry::OpeningBalance, MoneyEntry::Advance, MoneyEntry::AdjustmentUp], true)) {
                        $principalIn += $amountMinor;
                    } elseif ($entry === MoneyEntry::AdjustmentDown) {
                        $principalIn -= $amountMinor;
                    } elseif ($entry->isCharge() || $entry === MoneyEntry::Charge) {
                        $chargesIn += $amountMinor;
                    } elseif ($entry->isSettlement()) {
                        $settledTotal += $amountMinor;
                    }
                }

                $principalIn = max(0, $principalIn);
                $chargesIn = max(0, $chargesIn);
                $settledTotal = max(0, $settledTotal);

                if ($allocationOrder === 'principal_first') {
                    $unpaidPrincipal = max(0, $principalIn - min($settledTotal, $principalIn));
                    $settledAgainstCharges = max(0, $settledTotal - $principalIn);
                    $unpaidCharges = max(0, $chargesIn - min($settledAgainstCharges, $chargesIn));
                } else {
                    $unpaidCharges = max(0, $chargesIn - min($settledTotal, $chargesIn));
                    $settledAgainstPrincipal = max(0, $settledTotal - $chargesIn);
                    $unpaidPrincipal = max(0, $principalIn - min($settledAgainstPrincipal, $principalIn));
                }

                $breakdowns[(string) $obligation->getKey()][$currency] = [
                    'principal_in' => $principalIn,
                    'charges_in' => $chargesIn,
                    'settled_total' => $settledTotal,
                    'unpaid_charges' => $unpaidCharges,
                    'unpaid_principal' => $unpaidPrincipal,
                ];
            }
        }

        return $breakdowns;
    }

    /**
     * @return array{
     *     principal_in: int,
     *     charges_in: int,
     *     settled_total: int,
     *     unpaid_charges: int,
     *     unpaid_principal: int
     * }
     */
    public function forCurrency(Obligation $obligation, string $currency): array
    {
        return $this->forObligation($obligation)[$currency] ?? [
            'principal_in' => 0,
            'charges_in' => 0,
            'settled_total' => 0,
            'unpaid_charges' => 0,
            'unpaid_principal' => 0,
        ];
    }

    public function outstandingPrincipal(Obligation $obligation, string $currency): int
    {
        return $this->forCurrency($obligation, $currency)['unpaid_principal'];
    }
}
