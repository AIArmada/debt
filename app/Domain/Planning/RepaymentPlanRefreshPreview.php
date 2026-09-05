<?php

namespace App\Domain\Planning;

use App\Models\Obligation;
use App\Models\RepaymentPlan;

final class RepaymentPlanRefreshPreview
{
    public function __construct(
        private readonly BudgetCapacity $budgetCapacity,
        private readonly RepaymentPlanner $planner,
    ) {}

    /** @return array{currency: string, previous_available: int, current_available: int, warning: string|null, rows: array<int, array{title: string, current: int, previous: int, proposed: int, currency: string}>, has_changes: bool} */
    public function build(RepaymentPlan $plan): array
    {
        $plan->load([
            'budgetPeriod.cashFlowEntries',
            'allocations.obligation.record',
        ]);
        $budget = $plan->budgetPeriod;

        if ($budget === null) {
            return [
                'currency' => strtoupper((string) $plan->currency),
                'previous_available' => (int) $plan->available_amount,
                'current_available' => 0,
                'warning' => 'The budget period for this plan is no longer available.',
                'rows' => [],
                'has_changes' => true,
            ];
        }

        $currency = strtoupper((string) $budget->currency);
        $capacity = $this->budgetCapacity->breakdown($budget);
        $oldAllocations = $plan->allocations
            ->filter(fn ($allocation): bool => strtoupper((string) ($allocation->currency ?: $plan->currency)) === $currency)
            ->values();
        $oldIds = $oldAllocations->pluck('obligation_id')->map(fn ($id): string => (string) $id);
        $obligations = $oldAllocations
            ->map(fn ($allocation): ?Obligation => $allocation->obligation)
            ->filter(fn (?Obligation $obligation): bool => $obligation instanceof Obligation && $this->isEligible($obligation, $currency))
            ->values();
        $result = $this->planner->plan($obligations, $capacity['available_to_plan'], $plan->strategy);
        $proposedById = collect($result['allocations'])->keyBy(fn (array $allocation): string => (string) $allocation['obligation_id']);
        $previousById = $oldAllocations->keyBy(fn ($allocation): string => (string) $allocation->obligation_id);
        $currentById = $obligations->keyBy(fn (Obligation $obligation): string => (string) $obligation->getKey());
        $ids = $oldIds->merge($proposedById->keys())->unique()->values();
        $rows = $ids->map(function (string $id) use ($currentById, $previousById, $proposedById, $currency): array {
            $obligation = $currentById->get($id);
            $previous = $previousById->get($id);
            $proposed = $proposedById->get($id);

            $title = $obligation instanceof Obligation
                ? (string) $obligation->title
                : ($previous === null ? 'Removed obligation' : (string) $previous->obligation->title);

            return [
                'title' => $title,
                'current' => $obligation instanceof Obligation ? $obligation->currentPositionAmount() : 0,
                'previous' => $previous === null ? 0 : (int) $previous->total_amount,
                'proposed' => (int) ($proposed['total_amount'] ?? 0),
                'currency' => $currency,
            ];
        })->values()->all();

        return [
            'currency' => $currency,
            'previous_available' => (int) $plan->available_amount,
            'current_available' => (int) $capacity['available_to_plan'],
            'warning' => $result['warning'],
            'rows' => $rows,
            'has_changes' => (int) $plan->available_amount !== (int) $capacity['available_to_plan']
                || collect($rows)->contains(fn (array $row): bool => $row['previous'] !== $row['proposed']),
        ];
    }

    private function isEligible(Obligation $obligation, string $currency): bool
    {
        return $obligation->obligation_kind === 'money'
            && $obligation->status === 'active'
            && ! $obligation->record->is_archived
            && strtoupper((string) $obligation->currency) === $currency
            && $obligation->currentPositionDirection() === 'payable';
    }
}
