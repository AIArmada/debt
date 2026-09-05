<?php

namespace App\Actions\Plans;

use App\Domain\Money\MoneyAmount;
use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateBudgetPeriod
{
    /** @param array{currency: string, starts_on: string, ends_on: string, emergency_reserve_amount: string} $data */
    public function handle(User $user, FinancialProfile $profile, array $data): BudgetPeriod
    {
        Gate::forUser($user)->authorize('manageBudget', $profile);

        $currency = strtoupper($data['currency']);
        try {
            $reserve = MoneyAmount::fromMajorOrZero($data['emergency_reserve_amount'], $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['emergency_reserve_amount' => $exception->getMessage()]);
        }

        return $profile->budgetPeriods()->create([
            'currency' => $currency,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'emergency_reserve_amount' => $reserve,
            'status' => 'open',
        ]);
    }
}
