<?php

namespace Database\Factories;

use App\Models\MoneySubject;
use App\Models\Obligation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoneySubject> */
class MoneySubjectFactory extends Factory
{
    protected $model = MoneySubject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['obligation_id' => Obligation::factory(), 'currency' => 'MYR'];
    }
}
