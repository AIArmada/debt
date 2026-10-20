<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PlanStrategy;
use App\Domain\Enums\SubjectType;
use App\Models\BudgetPeriod;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\Record;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class DebtFreeProjection
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly BalanceBreakdown $balanceBreakdown,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
    ) {}

    /**
     * @return array{
     *     months_to_free: int,
     *     total_estimated_charges_minor: int,
     *     extra_capacity_minor: int,
     *     extra_months_to_free: int,
     *     months_earlier: int,
     *     charges_saved_minor: int,
     *     collect_first: list<array{title: string, party: string, amount_minor: int, currency: string}>
     * }
     */
    public function forPeriod(User $user, BudgetPeriod $period, PlanStrategy $strategy): array
    {
        $records = $this->recordVisibilityQuery
            ->forUser($period->profile->records()->getQuery(), $user)
            ->where('is_archived', false)
            ->with(['partyLinks.party', 'obligations.chargeTerms'])
            ->get();
        $obligations = $records->flatMap(static fn (Record $record): EloquentCollection => $record->obligations);
        $breakdowns = $this->balanceBreakdown->forObligations($obligations);
        $graceObligationIds = $obligations->filter(
            static fn (Obligation $obligation): bool => $obligation->chargeTerms instanceof ObligationChargeTerm
                && $obligation->chargeTerms->grace_days > 0,
        )->map(static fn (Obligation $obligation): string => (string) $obligation->getKey())
            ->values()
            ->all();
        $openingDates = $graceObligationIds === []
            ? []
            : MoneyMovement::query()
                ->whereIn('obligation_id', $graceObligationIds)
                ->where('currency', $period->currency)
                ->where('status', MovementStatus::Confirmed->value)
                ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
                ->selectRaw('obligation_id, MIN(occurred_on) AS opening_on')
                ->groupBy('obligation_id')
                ->pluck('opening_on', 'obligation_id')
                ->map(static fn (mixed $openingOn): CarbonImmutable => CarbonImmutable::parse((string) $openingOn))
                ->all();
        /** @var list<array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int}> $payables */
        $payables = [];
        /** @var list<array{title: string, party: string, amount_minor: int, currency: string}> $receivables */
        $receivables = [];

        foreach ($obligations as $obligation) {
            if ($obligation->subject_type !== SubjectType::Money || $obligation->status !== ObligationStatus::Open) {
                continue;
            }
            $balance = $this->outstandingBalance->forObligation($obligation)[$period->currency] ?? 0;
            if ($obligation->direction === Direction::Receivable && $balance > 0) {
                $record = $records->firstWhere('id', $obligation->record_id);
                if ($record === null) {
                    continue;
                }
                $party = $record->partyLinks
                    ->first(fn ($link): bool => $link->role === PartyRole::Counterparty && $link->is_primary)?->party;
                $receivables[] = [
                    'title' => $record->title,
                    'party' => $party->display_name ?? '',
                    'amount_minor' => $balance,
                    'currency' => $period->currency,
                ];
            }
            if ($obligation->direction !== Direction::Payable || $balance <= 0) {
                continue;
            }

            $breakdown = $breakdowns[(string) $obligation->getKey()][$period->currency] ?? [
                'principal_in' => 0,
                'charges_in' => 0,
                'unpaid_charges' => 0,
            ];
            $chargeBalance = min($balance, $breakdown['unpaid_charges']);
            $terms = $obligation->chargeTerms;
            $capRemaining = $terms instanceof ObligationChargeTerm && $terms->cap_minor !== null
                ? max(0, (int) $terms->cap_minor - (int) $breakdown['charges_in'])
                : null;
            $payables[] = [
                'id' => (string) $obligation->getKey(),
                'title' => $obligation->title,
                'due_on' => $obligation->due_on?->getTimestamp() ?? PHP_INT_MAX,
                'balance' => $balance,
                'principal_remaining' => max(0, $balance - $chargeBalance),
                'charges_remaining' => $chargeBalance,
                'original_principal' => max(0, $breakdown['principal_in']),
                'annual_rate' => $this->annualRate($obligation),
                'basis' => $obligation->chargeTerms->basis ?? null,
                'allocation_order' => $obligation->chargeTerms->allocation_order ?? 'charges_first',
                'cap_remaining' => $capRemaining,
                'grace_months_remaining' => $this->graceMonthsRemaining(
                    $openingDates[(string) $obligation->getKey()] ?? null,
                    $terms instanceof ObligationChargeTerm ? (int) $terms->grace_days : 0,
                ),
            ];
        }

        usort($receivables, static fn (array $left, array $right): int => $right['amount_minor'] <=> $left['amount_minor']);
        $base = $this->simulate($payables, $period->capacity(), $strategy);
        $extraCapacity = (int) bcdiv(bcadd(bcmul((string) $period->capacity(), '110', 0), '99', 0), '100', 0);
        $extra = $this->simulate($payables, $extraCapacity, $strategy);

        return [
            'months_to_free' => $base['months'],
            'total_estimated_charges_minor' => $base['charges'],
            'extra_capacity_minor' => $extraCapacity,
            'extra_months_to_free' => $extra['months'],
            'months_earlier' => max(0, $base['months'] - $extra['months']),
            'charges_saved_minor' => max(0, $base['charges'] - $extra['charges']),
            'collect_first' => array_slice($receivables, 0, 3),
        ];
    }

    /** @param list<array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int}> $initialDebts
     * @return array{months: int, charges: int}
     */
    private function simulate(array $initialDebts, int $capacity, PlanStrategy $strategy): array
    {
        $debts = array_values(array_filter($initialDebts, static fn (array $debt): bool => (int) $debt['balance'] > 0));
        if ($debts === []) {
            return ['months' => 0, 'charges' => 0];
        }

        $estimatedCharges = 0;
        for ($month = 1; $month <= 600; $month++) {
            foreach ($debts as &$debt) {
                $charge = $month <= $debt['grace_months_remaining']
                    ? 0
                    : $this->monthlyCharge($debt);
                if ($debt['cap_remaining'] !== null) {
                    $charge = min($charge, $debt['cap_remaining']);
                    $debt['cap_remaining'] = max(0, $debt['cap_remaining'] - $charge);
                }
                $debt['charges_remaining'] = (int) $debt['charges_remaining'] + $charge;
                $debt['balance'] = (int) $debt['principal_remaining'] + (int) $debt['charges_remaining'];
                $estimatedCharges += $charge;
            }
            unset($debt);

            $debts = $this->sortDebts($debts, $strategy);
            $remainingCapacity = $capacity;
            foreach ($debts as &$debt) {
                if ($remainingCapacity <= 0) {
                    break;
                }
                $paid = min($remainingCapacity, (int) $debt['balance']);
                $this->applyPayment($debt, $paid);
                $remainingCapacity -= $paid;
            }
            unset($debt);
            $debts = array_values(array_filter($debts, static fn (array $debt): bool => (int) $debt['balance'] > 0));
            if ($debts === []) {
                return ['months' => $month, 'charges' => $estimatedCharges];
            }
        }

        return ['months' => 600, 'charges' => $estimatedCharges];
    }

    /** @param array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int} $debt */
    private function monthlyCharge(array $debt): int
    {
        $basis = (string) ($debt['basis'] ?? '');
        $annualRate = (string) $debt['annual_rate'];
        if (bccomp($annualRate, '0', 12) === 0 || $basis === 'fixed_total') {
            return 0;
        }

        $amount = match ($basis) {
            'flat' => (int) $debt['principal_remaining'] > 0 ? (int) $debt['original_principal'] : 0,
            'reducing_simple' => (int) $debt['principal_remaining'],
            default => 0,
        };
        if ($amount <= 0) {
            return 0;
        }

        $raw = bcdiv(bcmul((string) $amount, $annualRate, 12), '12', 12);
        $whole = (int) bcadd($raw, '0', 0);
        $fraction = bcsub($raw, (string) $whole, 12);

        return bccomp($fraction, '0.5', 12) >= 0 ? $whole + 1 : $whole;
    }

    /** @param array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int} $debt */
    private function applyPayment(array &$debt, int $amount): void
    {
        if ($debt['allocation_order'] === 'principal_first') {
            $principalPaid = min($amount, (int) $debt['principal_remaining']);
            $debt['principal_remaining'] = (int) $debt['principal_remaining'] - $principalPaid;
            $debt['charges_remaining'] = max(0, (int) $debt['charges_remaining'] - ($amount - $principalPaid));
        } else {
            $chargesPaid = min($amount, (int) $debt['charges_remaining']);
            $debt['charges_remaining'] = (int) $debt['charges_remaining'] - $chargesPaid;
            $debt['principal_remaining'] = max(0, (int) $debt['principal_remaining'] - ($amount - $chargesPaid));
        }
        $debt['balance'] = (int) $debt['principal_remaining'] + (int) $debt['charges_remaining'];
    }

    /** @param list<array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int}> $debts
     * @return list<array{id: string, title: string, due_on: int, balance: int, principal_remaining: int, charges_remaining: int, original_principal: int, annual_rate: numeric-string, basis: string|null, allocation_order: string, cap_remaining: int|null, grace_months_remaining: int}>
     */
    private function sortDebts(array $debts, PlanStrategy $strategy): array
    {
        usort($debts, static function (array $left, array $right) use ($strategy): int {
            $strategyOrder = match ($strategy) {
                PlanStrategy::DueDate => 0,
                PlanStrategy::Snowball => (int) $left['balance'] <=> (int) $right['balance'],
                PlanStrategy::Avalanche => bccomp($right['annual_rate'], $left['annual_rate'], 12),
            };

            $dateOrder = (int) $left['due_on'] <=> (int) $right['due_on'];
            $dueDateBalanceOrder = $strategy === PlanStrategy::DueDate
                ? (int) $right['balance'] <=> (int) $left['balance']
                : 0;

            return $strategyOrder
                ?: $dateOrder
                ?: $dueDateBalanceOrder
                ?: strcmp((string) $left['id'], (string) $right['id']);
        });

        return $debts;
    }

    /** @return numeric-string */
    private function annualRate(Obligation $obligation): string
    {
        $terms = $obligation->chargeTerms;
        if ($terms === null || $terms->rate === null || $terms->basis === 'fixed_total' || $terms->kind === 'none') {
            return '0';
        }
        $multiplier = match ($terms->period) {
            'daily' => '365',
            'monthly' => '12',
            default => '1',
        };
        $rate = (string) $terms->rate;
        if (! is_numeric($rate)) {
            return '0';
        }

        return bcmul($rate, $multiplier, 12);
    }

    /** Each obligation's remaining grace starts from that obligation's first confirmed opening movement. */
    private function graceMonthsRemaining(?CarbonImmutable $openingDate, int $graceDays): int
    {
        if ($graceDays <= 0 || $openingDate === null) {
            return 0;
        }

        $graceEndsOn = $openingDate->addDays($graceDays);
        $today = CarbonImmutable::today();
        if (! $graceEndsOn->greaterThan($today)) {
            return 0;
        }

        return (int) ceil((int) $today->diff($graceEndsOn)->days / 30);
    }
}
