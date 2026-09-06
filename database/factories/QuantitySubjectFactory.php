<?php

namespace Database\Factories;

use App\Models\Obligation;
use App\Models\QuantitySubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuantitySubject> */
class QuantitySubjectFactory extends Factory
{
    protected $model = QuantitySubject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'name' => fake()->word(),
            'total' => '1.0000',
            'unit' => 'item',
            'is_fractionable' => false,
        ];
    }
}
