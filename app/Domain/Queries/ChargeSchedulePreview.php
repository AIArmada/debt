<?php

namespace App\Domain\Queries;

use App\Domain\Charges\ChargeAnniversary;
use App\Domain\Charges\ChargeCalculator;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ChargeSchedulePreview
{
    public function __construct(
        private readonly ChargeCalculator $calculator,
        private readonly BalanceBreakdown $balanceBreakdown,
    ) {}

    /**
     * @return list<array{
     *     period_start: string,
     *     period_end: string,
     *     amount_minor: int,
     *     currency: string,
     *     basis: string,
     *     estimated: bool
     * }>
     */
    public function forTerms(
        Obligation $obligation,
        ObligationChargeTerm $terms,
        string $currency,
        ?CarbonInterface $fromDate = null,
    ): array {
        $today = CarbonImmutable::parse(($fromDate ?? today())->toDateString());
        $principalMinor = $this->principalTotal($obligation, $currency);
        $originalTerms = $obligation->chargeTerms;
        $obligation->setRelation('chargeTerms', $terms);
        $balance = $this->balanceBreakdown->forCurrency($obligation, $currency);
        $obligation->setRelation('chargeTerms', $originalTerms);
        $alreadyCharged = $balance['charges_in'];
        $reservedProposed = $this->proposedChargesTotal($obligation, $currency);

        if ($terms->basis === 'fixed_total') {
            if ($terms->fixed_total_posted_at !== null || $terms->total_payable_minor === null) {
                return [];
            }

            $profitMinor = (int) $terms->total_payable_minor - $principalMinor;
            if ($profitMinor <= 0) {
                return [];
            }

            return [[
                'period_start' => $today->toDateString(),
                'period_end' => $today->toDateString(),
                'amount_minor' => $profitMinor,
                'currency' => $currency,
                'basis' => 'fixed_total',
                'estimated' => true,
            ]];
        }

        $rate = $terms->rate === null ? null : (string) $terms->rate;
        if (! in_array($terms->basis, ['flat', 'reducing_simple'], true)
            || ! in_array($terms->period, ['daily', 'monthly', 'yearly'], true)
            || $rate === null
            || ! is_numeric($rate)
            || bccomp($rate, '0', 8) <= 0) {
            return [];
        }

        $openingDate = $this->openingDate($obligation, $currency);
        if ($openingDate === null) {
            return [];
        }

        $anchor = $openingDate->addDays((int) $terms->grace_days);
        $hasGeneratedSchedule = $terms->last_charge_on !== null || $terms->next_charge_on !== null;
        $initialStart = $terms->last_charge_on === null
            ? $anchor
            : CarbonImmutable::parse($terms->last_charge_on->toDateString());
        $start = $initialStart;
        if ($hasGeneratedSchedule) {
            $next = $this->nextPeriod($anchor, $start, (string) $terms->period);
            while (! $next->greaterThan($today)) {
                $start = $next;
                $next = $this->nextPeriod($anchor, $start, (string) $terms->period);
            }
        } else {
            $start = $initialStart->greaterThan($today) ? $initialStart : $today;
            $next = $this->nextPeriod($start, $start, (string) $terms->period);
        }

        $preview = [];
        $calculationAnchor = $hasGeneratedSchedule ? $anchor : $start;
        for ($periodNumber = 0; $periodNumber < 6; $periodNumber++) {
            $calculation = $this->calculator->calculate(
                $terms,
                $currency,
                $start,
                $next,
                $principalMinor,
                $balance['unpaid_principal'],
                $alreadyCharged,
                $reservedProposed,
                $calculationAnchor,
            );
            if ($calculation['amount_minor'] <= 0) {
                break;
            }

            $preview[] = [
                'period_start' => $start->toDateString(),
                'period_end' => $next->toDateString(),
                'amount_minor' => $calculation['amount_minor'],
                'currency' => $currency,
                'basis' => (string) $terms->basis,
                'estimated' => true,
            ];
            $reservedProposed += $calculation['amount_minor'];
            $start = $next;
            $next = $this->nextPeriod($calculationAnchor, $start, (string) $terms->period);
        }

        return $preview;
    }

    private function principalTotal(Obligation $obligation, string $currency): int
    {
        return (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->sum('amount_minor');
    }

    private function openingDate(Obligation $obligation, string $currency): ?CarbonImmutable
    {
        $date = MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->orderBy('occurred_on')
            ->value('occurred_on');

        return $date === null ? null : CarbonImmutable::parse((string) $date);
    }

    private function proposedChargesTotal(Obligation $obligation, string $currency): int
    {
        return (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Proposed->value)
            ->whereIn('entry', [
                MoneyEntry::Charge->value,
                MoneyEntry::InterestCharge->value,
                MoneyEntry::ProfitCharge->value,
                MoneyEntry::Fee->value,
                MoneyEntry::Penalty->value,
            ])
            ->sum('amount_minor');
    }

    private function nextPeriod(CarbonImmutable $anchor, CarbonImmutable $date, string $period): CarbonImmutable
    {
        return ChargeAnniversary::nextAfter($anchor, $date, $period);
    }
}
