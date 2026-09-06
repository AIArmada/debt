<?php

namespace Database\Factories;

use App\Domain\Enums\PlanStatus;
use App\Models\BudgetPeriod;
use App\Models\RepaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepaymentPlan> */
class RepaymentPlanFactory extends Factory
{
    protected $model = RepaymentPlan::class;

    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'status' => PlanStatus::Draft, 'generated_at' => null];
    }
}
