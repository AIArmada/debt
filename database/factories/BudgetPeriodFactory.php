<?php

namespace Database\Factories;

use App\Models\BudgetPeriod;
use App\Models\FinancialProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetPeriod> */
class BudgetPeriodFactory extends Factory
{
    protected $model = BudgetPeriod::class;

    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'starts_on' => today()->startOfMonth(),
            'ends_on' => today()->endOfMonth(),
            'income_minor' => 100000,
            'essential_minor' => 50000,
            'reserve_minor' => 10000,
            'currency' => 'MYR',
        ];
    }
}
