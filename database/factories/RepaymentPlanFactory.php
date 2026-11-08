<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\PlanStatus;
use App\Features\PromiseTracker\Domain\Enums\PlanStrategy;
use App\Features\PromiseTracker\Models\BudgetPeriod;
use App\Features\PromiseTracker\Models\RepaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepaymentPlan> */
class RepaymentPlanFactory extends Factory
{
    protected $model = RepaymentPlan::class;

    public function definition(): array
    {
        return ['budget_period_id' => BudgetPeriod::factory(), 'status' => PlanStatus::Draft, 'strategy' => PlanStrategy::DueDate, 'generated_at' => null];
    }
}
