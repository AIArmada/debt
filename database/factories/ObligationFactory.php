<?php

namespace Database\Factories;

use App\Models\Obligation;
use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Obligation>
 */
class ObligationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_id' => Record::factory(),
            'direction' => 'payable',
            'obligation_kind' => 'money',
            'category' => 'personal_loan',
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'tracking_mode' => 'snapshot',
            'currency' => 'MYR',
            'original_amount' => 100000,
            'current_principal_balance' => 100000,
            'current_total_balance' => 100000,
            'currency_opening_balances' => ['MYR' => 100000],
            'currency_balances' => ['MYR' => 100000],
            'data_confidence' => 'partial',
            'is_interest_bearing' => false,
        ];
    }
}
