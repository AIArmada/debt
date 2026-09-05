<?php

namespace App\Actions\Plans;

use App\Domain\Planning\BudgetCapacity;
use App\Domain\Planning\RepaymentPlanner;
use App\Models\BudgetPeriod;
use App\Models\FinancialTransaction;
use App\Models\RepaymentPlan;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class GenerateRepaymentPlan
{
    public function __construct(
        private readonly RepaymentPlanner $planner,
        private readonly BudgetCapacity $budgetCapacity,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<int, string> $obligationIds */
    public function handle(BudgetPeriod $budgetPeriod, string $strategy, array $obligationIds, ?RepaymentPlan $refreshingPlan = null): RepaymentPlan
    {
        Gate::authorize('manageBudget', $budgetPeriod->profile);
        $budgetPeriod->load('cashFlowEntries', 'profile.records.obligations');
        $availableAmount = $this->budgetCapacity->calculate($budgetPeriod);
        $selectedIds = collect($obligationIds)->map(fn ($id): string => (string) $id)->filter()->values();

        if ($selectedIds->isEmpty()) {
            throw new InvalidArgumentException('A repayment plan must include at least one obligation.');
        }

        $obligations = $budgetPeriod->profile->records
            ->flatMap(fn ($record) => $record->obligations)
            ->filter(function ($obligation) use ($budgetPeriod, $selectedIds): bool {
                $isEligible = $obligation->obligation_kind === 'money'
                    && $obligation->status === 'active'
                    && ! $obligation->record->is_archived
                    && strtoupper((string) $obligation->currency) === strtoupper((string) $budgetPeriod->currency)
                    && $obligation->currentPositionDirection() === 'payable';

                return $isEligible && $selectedIds->contains((string) $obligation->getKey());
            })
            ->values();

        if ($obligations->isEmpty()) {
            throw new InvalidArgumentException('The selected obligations are not eligible for this repayment plan.');
        }

        $result = $this->planner->plan(
            $obligations,
            $availableAmount,
            $strategy,
        );
        $paidByObligation = $this->paidByObligationInPeriod($budgetPeriod, $obligations->pluck('id')->map(fn ($id): string => (string) $id)->all());
        $allocations = collect($result['allocations'])->map(function (array $allocation) use ($paidByObligation): array {
            return [
                ...$allocation,
                'carried_paid_amount' => $paidByObligation[(string) $allocation['obligation_id']] ?? 0,
            ];
        })->all();

        return DB::transaction(function () use ($budgetPeriod, $availableAmount, $strategy, $allocations, $refreshingPlan): RepaymentPlan {
            $previousActivePlan = RepaymentPlan::query()
                ->where('profile_id', $budgetPeriod->profile_id)
                ->where('budget_period_id', $budgetPeriod->getKey())
                ->where('currency', $budgetPeriod->currency)
                ->where('status', 'active')
                ->latest('generated_at')
                ->first();

            $plan = $budgetPeriod->profile->repaymentPlans()->create([
                'budget_period_id' => $budgetPeriod->getKey(),
                'name' => 'Plan for '.CarbonImmutable::parse((string) $budgetPeriod->getAttribute('starts_on'))->format('d M Y'),
                'strategy' => $strategy,
                'available_amount' => $availableAmount,
                'currency' => $budgetPeriod->currency,
                'status' => 'active',
                'activated_at' => now(),
                'generated_at' => now(),
            ]);

            $plan->allocations()->createMany($allocations);

            if ($previousActivePlan !== null) {
                $previousBefore = $previousActivePlan->only(['status', 'paused_at']);
                $previousActivePlan->forceFill([
                    'status' => 'paused',
                    'paused_at' => now(),
                ])->save();

                $this->auditLogger->record(
                    $budgetPeriod->profile,
                    null,
                    RepaymentPlan::class,
                    $previousActivePlan->getKey(),
                    'paused_for_new_plan',
                    before: $previousBefore,
                    after: $previousActivePlan->only(['status', 'paused_at']),
                    metadata: ['replacement_plan_id' => $plan->getKey()],
                );
            }

            if ($refreshingPlan !== null) {
                $sourcePlan = RepaymentPlan::query()
                    ->whereKey($refreshingPlan->getKey())
                    ->where('profile_id', $budgetPeriod->profile_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $sourceBefore = $sourcePlan->only(['status', 'superseded_at']);
                $sourcePlan->forceFill([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                ])->save();

                $this->auditLogger->record(
                    $budgetPeriod->profile,
                    null,
                    RepaymentPlan::class,
                    $sourcePlan->getKey(),
                    'superseded_by_refresh',
                    before: $sourceBefore,
                    after: $sourcePlan->only(['status', 'superseded_at']),
                    metadata: ['replacement_plan_id' => $plan->getKey()],
                );
            }

            $this->auditLogger->record(
                $budgetPeriod->profile,
                null,
                RepaymentPlan::class,
                $plan->getKey(),
                'generated',
                after: $plan->only(['profile_id', 'budget_period_id', 'strategy', 'available_amount', 'currency', 'status']),
                metadata: [
                    'allocation_count' => count($allocations),
                    'paused_plan_id' => $previousActivePlan?->getKey(),
                    'refreshed_plan_id' => $refreshingPlan?->getKey(),
                ],
            );

            return $plan->load('allocations.obligation');
        });
    }

    /** @param array<int, string> $obligationIds
     * @return array<string, int>
     */
    private function paidByObligationInPeriod(BudgetPeriod $budgetPeriod, array $obligationIds): array
    {
        if ($obligationIds === []) {
            return [];
        }

        return FinancialTransaction::query()
            ->whereIn('obligation_id', $obligationIds)
            ->where('status', 'confirmed')
            ->where('entry_type', 'payment')
            ->where('currency', strtoupper((string) $budgetPeriod->currency))
            ->whereBetween('occurred_on', [$budgetPeriod->starts_on, $budgetPeriod->ends_on])
            ->get(['obligation_id', 'amount'])
            ->groupBy(fn (FinancialTransaction $transaction): string => (string) $transaction->obligation_id)
            ->map(fn ($transactions): int => $transactions->sum(fn (FinancialTransaction $transaction): int => (int) $transaction->amount))
            ->all();
    }
}
