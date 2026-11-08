<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\PlanAllocation;
use App\Features\PromiseTracker\Models\RepaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanAllocation> */
class PlanAllocationFactory extends Factory
{
    protected $model = PlanAllocation::class;

    public function definition(): array
    {
        return ['repayment_plan_id' => RepaymentPlan::factory(), 'obligation_id' => Obligation::factory(), 'planned_minor' => 1000];
    }
}
