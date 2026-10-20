<?php

namespace Database\Factories;

use App\Models\Instalment;
use App\Models\Obligation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Instalment> */
class InstalmentFactory extends Factory
{
    protected $model = Instalment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'sequence' => 1,
            'due_on' => today()->addMonth(),
            'amount_minor' => 10000,
            'currency' => 'MYR',
            'paid_minor' => 0,
        ];
    }
}
