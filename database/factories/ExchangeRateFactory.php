<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'from_currency' => 'USD',
            'to_currency' => 'MYR',
            'rate' => '4.70000000',
            'rated_on' => today(),
            'source' => 'user',
        ];
    }
}
