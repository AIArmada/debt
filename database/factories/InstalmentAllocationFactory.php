<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\Instalment;
use App\Features\PromiseTracker\Models\InstalmentAllocation;
use App\Features\PromiseTracker\Models\MoneyMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InstalmentAllocation> */
class InstalmentAllocationFactory extends Factory
{
    protected $model = InstalmentAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'money_movement_id' => MoneyMovement::factory(),
            'instalment_id' => Instalment::factory(),
            'amount_minor' => 1000,
        ];
    }
}
