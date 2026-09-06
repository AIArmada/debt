<?php

namespace Database\Factories;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoneyMovement> */
class MoneyMovementFactory extends Factory
{
    protected $model = MoneyMovement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'entry' => MoneyEntry::OpeningBalance,
            'amount_minor' => 10000,
            'currency' => 'MYR',
            'occurred_on' => today(),
            'status' => MovementStatus::Confirmed,
            'void_reason' => null,
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
