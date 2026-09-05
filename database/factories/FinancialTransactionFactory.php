<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use App\Models\Obligation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialTransaction>
 */
class FinancialTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'entry_type' => 'payment',
            'balance_effect' => 'decrease',
            'status' => 'confirmed',
            'amount' => 10000,
            'currency' => 'MYR',
            'principal_amount' => 10000,
            'occurred_on' => today()->toDateString(),
            'submitted_at' => now(),
            'confirmed_at' => now(),
            'external_reference' => null,
            'provider_payload' => null,
            'note' => null,
        ];
    }
}
