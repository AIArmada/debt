<?php

namespace App\Domain\Pawn;

use App\Domain\Calculations\AdvancedObligationCalculator;
use App\Domain\Money\Currency;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PawnRiskService
{
    public function __construct(private readonly AdvancedObligationCalculator $calculator) {}

    /** @return Collection<int, covariant array{obligation: Obligation, label: non-falsy-string, days: int|null, redemption_total: int|null, redemption_currency: string|null, redemption_label: non-falsy-string, asset_count: int<0, max>}> */
    public function forProfile(FinancialProfile $profile): Collection
    {
        return Obligation::query()
            ->whereHas('record', fn ($query) => $query->where('profile_id', $profile->getKey())->where('is_archived', false))
            ->active()
            ->whereIn('category', ['pawn_loan', 'pawned_asset'])
            ->select(['id', 'record_id', 'obligation_kind', 'currency', 'current_total_balance', 'minimum_payment_amount', 'due_on', 'next_due_on'])
            ->with([
                'record:id,profile_id,title,is_archived',
                'pledgedAssets' => fn ($query) => $query->select(['id', 'obligation_id', 'matures_on', 'currency', 'estimated_value']),
                'terms' => fn ($query) => $query->select([
                    'id', 'obligation_id', 'version', 'calculation_method', 'interest_rate', 'interest_period',
                    'compounding_period', 'late_fee_amount', 'late_fee_rate', 'storage_fee_amount', 'storage_fee_period',
                    'fixed_installment_amount', 'formula', 'effective_from',
                ]),
            ])
            ->get()
            ->filter(fn (Obligation $obligation): bool => ! $obligation->isPositionReversed())
            ->map(
                /** @return array<string, mixed> */
                function (Obligation $obligation): array {
                    $maturity = $obligation->pledgedAssets->pluck('matures_on')->filter()->sort()->first() ?? $obligation->due_on;
                    $days = $maturity === null ? null : (int) today()->diffInDays(CarbonImmutable::parse((string) $maturity), false);
                    $label = match (true) {
                        $days === null => 'Maturity date not recorded',
                        $days < 0 => 'Maturity passed '.abs($days).' days ago',
                        $days <= 7 => 'Matures in '.$days.' days',
                        $days <= 30 => 'Matures in '.$days.' days',
                        default => 'Matures on '.CarbonImmutable::parse((string) $maturity)->format('d M Y'),
                    };
                    [$redemption, $redemptionCurrency, $redemptionLabel] = $this->redemptionFor($obligation);

                    return [
                        'obligation' => $obligation,
                        'label' => $label,
                        'days' => $days,
                        'redemption_total' => $redemption,
                        'redemption_currency' => $redemptionCurrency,
                        'redemption_label' => $redemptionLabel,
                        'asset_count' => $obligation->pledgedAssets->count(),
                    ];
                }
            )
            ->sortBy(fn (array $risk): int => $risk['days'] ?? PHP_INT_MAX)
            ->values();
    }

    /** @return array{0: int|null, 1: string|null, 2: non-falsy-string} */
    private function redemptionFor(Obligation $obligation): array
    {
        if ($obligation->obligation_kind === 'money' && Currency::isSupported($obligation->currency)) {
            $obligation->setAttribute('minimum_payment_amount', 0);
            $projection = $this->calculator->project($obligation, $obligation->terms->first(), 1, '0');
            $redemption = $projection['starting_balance'] + $projection['interest_total'] + $projection['late_fee_total'] + $projection['storage_fee_total'];

            return [$redemption, strtoupper((string) $obligation->currency), 'Estimated redemption'];
        }

        $valuesByCurrency = [];
        foreach ($obligation->pledgedAssets as $asset) {
            $currency = strtoupper((string) $asset->currency);
            if ($asset->estimated_value !== null && Currency::isSupported($currency)) {
                $valuesByCurrency[$currency] = ($valuesByCurrency[$currency] ?? 0) + (int) $asset->estimated_value;
            }
        }

        if (count($valuesByCurrency) === 1) {
            $currency = array_key_first($valuesByCurrency);

            return [$valuesByCurrency[$currency], $currency, 'Estimated item value'];
        }

        return [null, null, count($valuesByCurrency) > 1 ? 'Values kept in native currencies' : 'Money value not recorded'];
    }
}
