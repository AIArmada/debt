<?php

namespace App\Actions\Plans;

use App\Domain\Money\MoneyAmount;
use App\Models\BudgetPeriod;
use App\Models\CashFlowEntry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddCashFlowEntry
{
    /** @param array{type: string, category: string, name: string, amount: string, is_essential: bool, is_recurring: bool} $data */
    public function handle(BudgetPeriod $budgetPeriod, array $data): CashFlowEntry
    {
        Gate::authorize('manageBudget', $budgetPeriod->profile);

        try {
            $amount = MoneyAmount::fromMajor($data['amount'], (string) $budgetPeriod->currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return $budgetPeriod->cashFlowEntries()->create([
            'type' => $data['type'],
            'category' => $data['category'],
            'name' => $data['name'],
            'amount' => $amount,
            'is_essential' => $data['is_essential'],
            'is_recurring' => $data['is_recurring'],
        ]);
    }
}
