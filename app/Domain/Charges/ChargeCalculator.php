<?php

namespace App\Domain\Charges;

use App\Models\ObligationChargeTerm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class ChargeCalculator
{
    private const int SCALE = 16;

    /**
     * @return array{
     *     amount_minor: int,
     *     breakdown: array{
     *         basis: string,
     *         basis_amount_minor: int,
     *         currency: string,
     *         rate: string|null,
     *         period: string|null,
     *         days: int,
     *         whole_periods: int,
     *         remainder_days: int,
     *         period_factor: numeric-string,
     *         window_start: string,
     *         window_end: string,
     *         cap_minor: int|null,
     *         already_charged_minor: int,
     *         already_charged_confirmed_minor: int,
     *         reserved_proposed_minor: int,
     *         cap_remaining_before_minor: int|null,
     *         cap_applied: bool
     *     }
     * }
     */
    public function calculate(
        ObligationChargeTerm $terms,
        string $currency,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        int $openingPrincipalTotalMinor,
        int $currentOutstandingPrincipalMinor,
        int $alreadyChargedConfirmedTotalMinor,
        int $reservedProposedTotalMinor = 0,
        ?CarbonInterface $anniversaryAnchor = null,
    ): array {
        $basis = (string) $terms->basis;
        $period = (string) $terms->period;
        $start = CarbonImmutable::parse($windowStart->toDateString());
        $end = CarbonImmutable::parse($windowEnd->toDateString());
        $anchor = CarbonImmutable::parse(($anniversaryAnchor ?? $windowStart)->toDateString());
        $days = $end->greaterThan($start) ? (int) $start->diff($end)->days : 0;
        $basisAmountMinor = match ($basis) {
            'flat' => max(0, $openingPrincipalTotalMinor),
            'reducing_simple' => max(0, $currentOutstandingPrincipalMinor),
            'fixed_total' => 0,
            default => throw new InvalidArgumentException('Charge terms need a supported basis.'),
        };
        $wholePeriods = 0;
        $remainderDays = 0;
        $periodFactor = '0';

        if ($basis !== 'fixed_total' && $days > 0) {
            if (! in_array($period, ['daily', 'monthly', 'yearly'], true)) {
                throw new InvalidArgumentException('Charge terms need a supported period.');
            }

            if ($period === 'daily') {
                $periodFactor = (string) $days;
                $wholePeriods = $days;
            } else {
                [$wholePeriods, $remainderDays, $periodFactor] = $this->periodFactor($start, $end, $period, $anchor);
            }
        }

        $rate = $terms->rate === null ? null : (string) $terms->rate;
        $amountMinor = 0;
        if ($basis !== 'fixed_total'
            && $basisAmountMinor > 0
            && $rate !== null
            && is_numeric($rate)
            && bccomp($rate, '0', self::SCALE) > 0) {
            $rawAmount = bcmul(
                bcmul((string) $basisAmountMinor, $rate, self::SCALE),
                $periodFactor,
                self::SCALE,
            );
            $amountMinor = (int) bcadd($rawAmount, '0.5', 0);
        }

        $capMinor = $terms->cap_minor === null ? null : (int) $terms->cap_minor;
        $alreadyCharged = max(0, $alreadyChargedConfirmedTotalMinor);
        $reservedProposed = max(0, $reservedProposedTotalMinor);
        $capUsed = $alreadyCharged + $reservedProposed;
        $capRemaining = $capMinor === null ? null : max(0, $capMinor - $capUsed);
        $capApplied = $capRemaining !== null && $amountMinor > $capRemaining;
        if ($capApplied) {
            $amountMinor = $capRemaining;
        }

        return [
            'amount_minor' => max(0, $amountMinor),
            'breakdown' => [
                'basis' => $basis,
                'basis_amount_minor' => $basisAmountMinor,
                'currency' => $currency,
                'rate' => $rate,
                'period' => $terms->period === null ? null : $period,
                'days' => $days,
                'whole_periods' => $wholePeriods,
                'remainder_days' => $remainderDays,
                'period_factor' => $periodFactor,
                'window_start' => $start->toDateString(),
                'window_end' => $end->toDateString(),
                'cap_minor' => $capMinor,
                'already_charged_minor' => $capUsed,
                'already_charged_confirmed_minor' => $alreadyCharged,
                'reserved_proposed_minor' => $reservedProposed,
                'cap_remaining_before_minor' => $capRemaining,
                'cap_applied' => $capApplied,
            ],
        ];
    }

    /**
     * @return array{int, int, numeric-string}
     */
    private function periodFactor(CarbonImmutable $start, CarbonImmutable $end, string $period, CarbonImmutable $anchor): array
    {
        if (! $end->greaterThan($start)) {
            return [0, 0, '0'];
        }

        $cursor = $start;
        $wholePeriods = 0;

        while (true) {
            $next = ChargeAnniversary::nextAfter($anchor, $cursor, $period);
            if ($next->greaterThan($end)) {
                break;
            }

            $cursor = $next;
            $wholePeriods++;
        }

        $remainderDays = $end->greaterThan($cursor) ? (int) $cursor->diff($end)->days : 0;
        $factor = (string) $wholePeriods;

        if ($remainderDays > 0) {
            $next = ChargeAnniversary::nextAfter($anchor, $cursor, $period);
            $daysInPeriod = max(1, (int) $cursor->diff($next)->days);
            $remainder = bcdiv((string) $remainderDays, (string) $daysInPeriod, self::SCALE);
            $factor = bcadd($factor, $remainder, self::SCALE);
        }

        return [$wholePeriods, $remainderDays, $factor];
    }
}
