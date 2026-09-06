<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\RepaymentPlan;
use Illuminate\Support\Facades\DB;

final class PlanProgress
{
    /** @return array<string, array{planned_minor: int, paid_minor: int}> */
    public function forPlan(RepaymentPlan $plan): array
    {
        $plan->loadMissing('budgetPeriod', 'allocations.obligation');
        $progress = [];
        foreach ($plan->allocations as $allocation) {
            $obligation = $allocation->obligation;
            $entry = $obligation->direction === Direction::Payable ? MoneyEntry::Payment : MoneyEntry::Collection;
            $paid = DB::table('money_movements')
                ->where('obligation_id', $obligation->getKey())
                ->where('currency', $plan->budgetPeriod->currency)
                ->where('entry', $entry->value)
                ->where('status', MovementStatus::Confirmed->value)
                ->whereBetween('occurred_on', [$plan->budgetPeriod->starts_on->toDateString(), $plan->budgetPeriod->ends_on->toDateString()])
                ->sum('amount_minor');
            $progress[$allocation->getKey()] = ['planned_minor' => $allocation->planned_minor, 'paid_minor' => (int) $paid];
        }

        return $progress;
    }
}
