<?php

namespace Database\Factories;

use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ObligationChargeTerm> */
class ObligationChargeTermFactory extends Factory
{
    protected $model = ObligationChargeTerm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'mode' => 'none',
            'kind' => 'none',
            'rate' => null,
            'basis' => null,
            'period' => null,
            'grace_days' => 0,
            'cap_minor' => null,
            'total_payable_minor' => null,
            'penalty_kind' => 'none',
            'penalty_value' => null,
            'allocation_order' => 'charges_first',
            'is_formal' => false,
            'auto_apply_on_acceptance' => false,
            'next_charge_on' => null,
            'last_charge_on' => null,
            'terms_hash' => null,
            'accepted_terms_hash' => null,
            'accepted_at' => null,
            'accepted_name' => null,
            'fixed_total_agreed_upfront_at' => null,
            'fixed_total_agreed_upfront_by' => null,
            'fixed_total_posted_at' => null,
        ];
    }
}
