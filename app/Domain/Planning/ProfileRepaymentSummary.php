<?php

namespace App\Domain\Planning;

use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\RepaymentPlan;
use App\Models\RepaymentPlanAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ProfileRepaymentSummary
{
    /**
     * Money obligations that can be included in a budget plan.
     *
     * A plan has one currency. The obligation's primary native currency must
     * match it, while other native currency positions stay visible separately.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function candidateRows(BudgetPeriod $budgetPeriod, ?RepaymentPlan $plan = null): Collection
    {
        $currency = strtoupper((string) $budgetPeriod->currency);
        $plannedByObligation = $this->plannedByObligation($plan);

        return $this->obligations($budgetPeriod->profile)->toBase()
            ->filter(fn (Obligation $obligation): bool => strtoupper((string) $obligation->currency) === $currency
                && $obligation->currentPositionDirection() === 'payable'
            )
            ->map(fn (Obligation $obligation): array => $this->candidateRow($obligation, $budgetPeriod, $plannedByObligation, $currency))
            ->sortBy([
                ['due_on', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * Other native currency positions deliberately excluded from this budget.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function excludedCurrencyRows(BudgetPeriod $budgetPeriod): Collection
    {
        $budgetCurrency = strtoupper((string) $budgetPeriod->currency);

        return $this->obligations($budgetPeriod->profile)->toBase()
            ->flatMap(fn (Obligation $obligation): array => $this->excludedRowsForObligation($obligation, $budgetCurrency))
            ->sortBy([
                ['currency', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /**
     * Profile totals that remain separated by native currency.
     *
     * @return Collection<string, array{currency: string, payable: int, receivable: int, minimum: int, planned: int, paid: int, actual_paid: int, remaining: int}>
     */
    public function profileSummary(FinancialProfile $profile, ?BudgetPeriod $budgetPeriod = null, ?RepaymentPlan $plan = null): Collection
    {
        $rows = [];
        $actualPaidByCurrency = $budgetPeriod === null ? [] : $this->actualPaymentsByCurrency($budgetPeriod);
        $planPaidByCurrency = $plan === null
            ? []
            : $this->planProgress($plan)->groupBy('currency')->map(fn (Collection $progress): int => $progress->sum('paid'))->all();

        foreach ($this->obligations($profile) as $obligation) {
            if ($obligation->obligation_kind !== 'money') {
                continue;
            }

            foreach ($obligation->currencyPositions() as $position) {
                if ($position['direction'] === null) {
                    continue;
                }

                $currency = $position['currency'];
                $rows[$currency] ??= [
                    'currency' => $currency,
                    'payable' => 0,
                    'receivable' => 0,
                    'minimum' => 0,
                    'planned' => 0,
                    'paid' => 0,
                    'actual_paid' => 0,
                    'remaining' => 0,
                ];

                if ($position['direction'] === 'payable') {
                    $rows[$currency]['payable'] += (int) $position['amount'];
                } else {
                    $rows[$currency]['receivable'] += (int) $position['amount'];
                }

                if (strtoupper((string) $obligation->currency) === $currency && $obligation->currentPositionDirection() === 'payable') {
                    $rows[$currency]['minimum'] += $obligation->minimum_payment_amount === null
                        ? 0
                        : min((int) $obligation->minimum_payment_amount, (int) $position['amount']);
                }

            }
        }

        foreach ($actualPaidByCurrency as $currency => $paid) {
            $rows[$currency] ??= [
                'currency' => $currency,
                'payable' => 0,
                'receivable' => 0,
                'minimum' => 0,
                'planned' => 0,
                'paid' => 0,
                'actual_paid' => 0,
                'remaining' => 0,
            ];
            $rows[$currency]['actual_paid'] = (int) $paid;
        }

        foreach ($planPaidByCurrency as $currency => $paid) {
            $rows[$currency] ??= [
                'currency' => $currency,
                'payable' => 0,
                'receivable' => 0,
                'minimum' => 0,
                'planned' => 0,
                'paid' => 0,
                'actual_paid' => 0,
                'remaining' => 0,
            ];
            $rows[$currency]['paid'] = (int) $paid;
        }

        foreach ($this->plannedByCurrency($plan) as $currency => $planned) {
            $rows[$currency] ??= [
                'currency' => $currency,
                'payable' => 0,
                'receivable' => 0,
                'minimum' => 0,
                'planned' => 0,
                'paid' => 0,
                'actual_paid' => 0,
                'remaining' => 0,
            ];
            $rows[$currency]['planned'] += $planned;
        }

        foreach ($rows as &$row) {
            if ($plan === null) {
                $row['paid'] = 0;
            }
            $row['remaining'] = max(0, (int) $row['planned'] - (int) $row['paid']);
        }
        unset($row);

        return collect($rows)->sortKeys();
    }

    /**
     * Progress for each allocation in a generated plan.
     *
     * @return Collection<int, array{allocation: RepaymentPlanAllocation, obligation: Obligation, currency: string, planned: int, paid: int, remaining: int, state: string}>
     */
    public function planProgress(RepaymentPlan $plan): Collection
    {
        $plan->loadMissing([
            'budgetPeriod',
            'allocations.obligation.record.partyLinks.party',
            'allocations.paidTransactions',
        ]);

        return $plan->allocations->toBase()->map(function (RepaymentPlanAllocation $allocation) use ($plan): array {
            $currency = strtoupper((string) ($allocation->currency ?: $plan->currency));
            $period = $plan->budgetPeriod;
            $paid = (int) $allocation->carried_paid_amount + $allocation->paidTransactions
                ->filter(fn ($transaction): bool => $transaction->status === 'confirmed'
                    && $transaction->entry_type === 'payment'
                    && strtoupper((string) $transaction->currency) === $currency
                    && $period !== null
                    && $transaction->occurred_on !== null
                    && CarbonImmutable::parse((string) $transaction->occurred_on)->betweenIncluded(
                        CarbonImmutable::parse((string) $period->starts_on),
                        CarbonImmutable::parse((string) $period->ends_on),
                    )
                )
                ->sum(fn ($transaction): int => (int) $transaction->amount);

            return [
                'allocation' => $allocation,
                'obligation' => $allocation->obligation,
                'currency' => $currency,
                'planned' => (int) $allocation->total_amount,
                'paid' => (int) $paid,
                'remaining' => max(0, (int) $allocation->total_amount - (int) $paid),
                'state' => $this->progressState((int) $allocation->total_amount, (int) $paid),
            ];
        });
    }

    /**
     * Confirmed payments made during a budget period in its planning currency.
     */
    public function actualPayments(BudgetPeriod $budgetPeriod): int
    {
        return array_sum($this->actualPaymentsByCurrency($budgetPeriod));
    }

    /** @return array<string, int> */
    private function actualPaymentsByCurrency(BudgetPeriod $budgetPeriod): array
    {
        return FinancialTransaction::query()
            ->whereHas('obligation.record', fn ($query) => $query
                ->where('profile_id', $budgetPeriod->profile_id)
                ->where('is_archived', false))
            ->where('status', 'confirmed')
            ->where('entry_type', 'payment')
            ->whereBetween('occurred_on', [
                CarbonImmutable::parse((string) $budgetPeriod->starts_on)->toDateString(),
                CarbonImmutable::parse((string) $budgetPeriod->ends_on)->toDateString(),
            ])
            ->get(['amount', 'currency'])
            ->groupBy(fn (FinancialTransaction $transaction): string => strtoupper((string) $transaction->currency))
            ->map(fn (Collection $transactions): int => $transactions->sum(fn (FinancialTransaction $transaction): int => (int) $transaction->amount))
            ->all();
    }

    /** @return EloquentCollection<int, Obligation> */
    private function obligations(FinancialProfile $profile): EloquentCollection
    {
        return Obligation::query()
            ->whereHas('record', fn ($query) => $query
                ->where('profile_id', $profile->getKey())
                ->where('is_archived', false))
            ->where('status', 'active')
            ->with(['record.partyLinks.party', 'transactions'])
            ->get();
    }

    /** @return array<string, int> */
    private function plannedByObligation(?RepaymentPlan $plan): array
    {
        if ($plan === null) {
            return [];
        }

        return $plan->allocations
            ->mapWithKeys(fn ($allocation): array => [(string) $allocation->obligation_id => (int) $allocation->total_amount])
            ->all();
    }

    /** @return array<string, int> */
    private function plannedByCurrency(?RepaymentPlan $plan): array
    {
        if ($plan === null) {
            return [];
        }

        return $plan->allocations
            ->groupBy(fn ($allocation): string => strtoupper((string) ($allocation->currency ?: $plan->currency)))
            ->map(fn (Collection $allocations): int => $allocations->sum(fn ($allocation): int => (int) $allocation->total_amount))
            ->all();
    }

    private function decreaseAmountInPeriod(Obligation $obligation, string $currency, BudgetPeriod $budgetPeriod): int
    {
        $entryType = $obligation->direction === 'payable' ? 'payment' : 'collection';
        $startsOn = CarbonImmutable::parse((string) $budgetPeriod->starts_on)->startOfDay();
        $endsOn = CarbonImmutable::parse((string) $budgetPeriod->ends_on)->endOfDay();

        return $obligation->transactions
            ->filter(fn ($transaction): bool => $transaction->status === 'confirmed'
                && $transaction->entry_type === $entryType
                && strtoupper((string) $transaction->currency) === strtoupper($currency)
                && $transaction->occurred_on !== null
                && CarbonImmutable::parse((string) $transaction->occurred_on)->betweenIncluded($startsOn, $endsOn)
            )
            ->sum(fn ($transaction): int => (int) $transaction->amount);
    }

    private function progressState(int $planned, int $paid): string
    {
        if ($planned <= 0) {
            return 'not funded';
        }

        if ($paid >= $planned) {
            return $paid > $planned ? 'over target' : 'paid';
        }

        return $paid > 0 ? 'partly paid' : 'not started';
    }

    /**
     * @param  array<string, int>  $plannedByObligation
     * @return array<string, mixed>
     */
    private function candidateRow(Obligation $obligation, BudgetPeriod $budgetPeriod, array $plannedByObligation, string $currency): array
    {
        $currentAmount = $obligation->currentPositionAmount();
        $minimumAmount = $obligation->minimum_payment_amount === null
            ? 0
            : min((int) $obligation->minimum_payment_amount, $currentAmount);

        return [
            'obligation' => $obligation,
            'record' => $obligation->record,
            'obligation_id' => (string) $obligation->getKey(),
            'record_id' => (string) $obligation->record_id,
            'title' => (string) $obligation->title,
            'party_name' => $obligation->record->primaryParty()?->preferred_name,
            'currency' => $currency,
            'current_amount' => $currentAmount,
            'minimum_amount' => $minimumAmount,
            'paid_this_period' => $this->decreaseAmountInPeriod($obligation, $currency, $budgetPeriod),
            'planned_amount' => $plannedByObligation[(string) $obligation->getKey()] ?? 0,
            'due_on' => $obligation->next_due_on,
        ];
    }

    /**
     * @param  array{currency: string, amount: int, direction: string|null}  $position
     * @return array<string, mixed>
     */
    private function excludedRow(Obligation $obligation, array $position): array
    {
        return [
            'obligation' => $obligation,
            'record' => $obligation->record,
            'obligation_id' => (string) $obligation->getKey(),
            'title' => (string) $obligation->title,
            'party_name' => $obligation->record->primaryParty()?->preferred_name,
            'currency' => $position['currency'],
            'amount' => $position['amount'],
            'direction' => $position['direction'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function excludedRowsForObligation(Obligation $obligation, string $budgetCurrency): array
    {
        return collect($obligation->currencyPositions())
            ->filter(fn (array $position): bool => $position['direction'] !== null && $position['currency'] !== $budgetCurrency)
            ->map(fn (array $position): array => $this->excludedRow($obligation, $position))
            ->all();
    }
}
