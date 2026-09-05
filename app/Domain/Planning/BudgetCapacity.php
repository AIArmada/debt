<?php

namespace App\Domain\Planning;

use App\Models\BudgetPeriod;
use Illuminate\Support\Facades\DB;

class BudgetCapacity
{
    public function calculate(BudgetPeriod $budgetPeriod): int
    {
        return $this->breakdown($budgetPeriod)['available_to_plan'];
    }

    /**
     * Show both the committed-cost capacity used by the planner and the more
     * conservative figure after every listed expense.
     *
     * @return array{income: int, essential_expenses: int, flexible_expenses: int, emergency_reserve: int, safe_capacity: int, actual_repayments: int, available_to_plan: int, recommended_capacity: int, recommended_after_repayments: int}
     */
    public function breakdown(BudgetPeriod $budgetPeriod): array
    {
        $income = 0;
        $essentialExpenses = 0;
        $flexibleExpenses = 0;

        foreach ($budgetPeriod->cashFlowEntries as $entry) {
            $amount = (int) $entry->amount;

            if ($entry->type === 'income') {
                $income += $amount;
            } elseif ($entry->type === 'expense' && $entry->is_essential) {
                $essentialExpenses += $amount;
            } elseif ($entry->type === 'expense') {
                $flexibleExpenses += $amount;
            }
        }

        $reserve = (int) $budgetPeriod->emergency_reserve_amount;
        $actualRepayments = (int) DB::table('financial_transactions')
            ->join('obligations', 'obligations.id', '=', 'financial_transactions.obligation_id')
            ->join('records', 'records.id', '=', 'obligations.record_id')
            ->where('records.profile_id', $budgetPeriod->profile_id)
            ->where('records.is_archived', false)
            ->where('financial_transactions.status', 'confirmed')
            ->where('financial_transactions.entry_type', 'payment')
            ->where('financial_transactions.currency', strtoupper((string) $budgetPeriod->currency))
            ->whereBetween('financial_transactions.occurred_on', [$budgetPeriod->starts_on, $budgetPeriod->ends_on])
            ->sum('financial_transactions.amount');
        $safeCapacity = max(0, $income - $essentialExpenses - $reserve);
        $recommendedCapacity = max(0, $income - $essentialExpenses - $flexibleExpenses - $reserve);

        return [
            'income' => $income,
            'essential_expenses' => $essentialExpenses,
            'flexible_expenses' => $flexibleExpenses,
            'emergency_reserve' => $reserve,
            'safe_capacity' => $safeCapacity,
            'actual_repayments' => $actualRepayments,
            'available_to_plan' => max(0, $safeCapacity - $actualRepayments),
            'recommended_capacity' => $recommendedCapacity,
            'recommended_after_repayments' => max(0, $recommendedCapacity - $actualRepayments),
        ];
    }
}
