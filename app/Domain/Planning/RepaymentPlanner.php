<?php

namespace App\Domain\Planning;

use App\Models\Obligation;
use Illuminate\Support\Collection;

class RepaymentPlanner
{
    /**
     * @param  Collection<int, Obligation>  $obligations
     * @return array{allocations: list<array{obligation_id: string, currency: string, priority_rank: int, minimum_amount: int, extra_amount: int, total_amount: int, priority_reason: string}>, warning: string|null}
     */
    public function plan(Collection $obligations, int $availableAmount, string $strategy): array
    {
        $ordered = $obligations->sort(function (Obligation $left, Obligation $right) use ($strategy): int {
            return match ($strategy) {
                'smallest_balance' => $left->currentPositionAmount() <=> $right->currentPositionAmount(),
                'earliest_due' => $this->dueTimestamp($left) <=> $this->dueTimestamp($right),
                'highest_interest' => ((int) $right->is_interest_bearing) <=> ((int) $left->is_interest_bearing),
                default => 0,
            };
        })->values();

        $remaining = max(0, $availableAmount);
        $allocations = [];

        foreach ($ordered as $priorityIndex => $obligation) {
            $balance = $obligation->currentPositionAmount();
            $minimum = $obligation->minimum_payment_amount === null
                ? 0
                : min((int) $obligation->minimum_payment_amount, $balance);
            $minimumAllocation = min($minimum, $remaining);

            $allocationKey = (string) $obligation->getKey();
            $allocations[$allocationKey] = $this->buildAllocation(
                $obligation,
                $priorityIndex + 1,
                $minimumAllocation,
                $strategy,
                $minimumAllocation < $minimum,
            );
            $remaining -= $minimumAllocation;
        }

        $warning = null;

        if ($ordered->contains(fn (Obligation $obligation): bool => $allocations[(string) $obligation->getKey()]['minimum_amount'] < ($obligation->minimum_payment_amount === null ? 0 : (int) $obligation->minimum_payment_amount))) {
            $warning = 'Available funds do not cover every contractual minimum payment.';
        }

        foreach ($ordered as $obligation) {
            if ($remaining <= 0) {
                break;
            }

            $balance = $obligation->currentPositionAmount();
            $allocationKey = (string) $obligation->getKey();
            $unallocatedBalance = max(0, $balance - $allocations[$allocationKey]['total_amount']);
            $extra = min($remaining, $unallocatedBalance);
            $allocations[$allocationKey] = [
                ...$allocations[$allocationKey],
                'extra_amount' => $extra,
                'total_amount' => $allocations[$allocationKey]['total_amount'] + $extra,
            ];
            $remaining -= $extra;
        }

        return ['allocations' => array_values($allocations), 'warning' => $warning];
    }

    /** @return array{obligation_id: string, currency: string, priority_rank: int, minimum_amount: int, extra_amount: int, total_amount: int, priority_reason: string} */
    private function buildAllocation(Obligation $obligation, int $priorityRank, int $minimumAllocation, string $strategy, bool $shortfall): array
    {
        return [
            'obligation_id' => (string) $obligation->getKey(),
            'currency' => strtoupper((string) $obligation->currency),
            'priority_rank' => $priorityRank,
            'minimum_amount' => $minimumAllocation,
            'extra_amount' => 0,
            'total_amount' => $minimumAllocation,
            'priority_reason' => $this->reason($strategy, $obligation, $shortfall),
        ];
    }

    private function dueTimestamp(Obligation $obligation): int
    {
        $value = $obligation->getAttribute('next_due_on');

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_string($value) && ($timestamp = strtotime($value)) !== false) {
            return $timestamp;
        }

        return PHP_INT_MAX;
    }

    private function reason(string $strategy, Obligation $obligation, bool $shortfall): string
    {
        if ($shortfall) {
            return 'Minimum payment priority; budget shortfall remains.';
        }

        return match ($strategy) {
            'smallest_balance' => 'Smallest current balance first.',
            'earliest_due' => $obligation->next_due_on === null ? 'No due date recorded; placed after dated items.' : 'Earliest recorded due date first.',
            'highest_interest' => $obligation->is_interest_bearing ? 'Interest or charges marked; higher cost priority.' : 'No interest flag; follows higher-cost records.',
            default => 'Manual order based on the current record list.',
        };
    }
}
