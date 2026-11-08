<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\PaymentDestinationKind;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\PaymentDestination;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentDestination> */
class PaymentDestinationFactory extends Factory
{
    protected $model = PaymentDestination::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'kind' => PaymentDestinationKind::BankAccount,
            'label' => 'Main account',
            'details_encrypted' => '1234567894567',
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
