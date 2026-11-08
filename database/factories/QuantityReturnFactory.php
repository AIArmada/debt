<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\MovementStatus;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\QuantityReturn;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuantityReturn> */
class QuantityReturnFactory extends Factory
{
    protected $model = QuantityReturn::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'quantity' => '1.0000',
            'occurred_on' => today(),
            'note' => null,
            'status' => MovementStatus::Confirmed,
            'void_reason' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
