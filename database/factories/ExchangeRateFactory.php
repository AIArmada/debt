<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'from_currency' => 'USD',
            'to_currency' => 'MYR',
            'rate' => '4.70000000',
            'rated_on' => today(),
            'source' => 'user',
        ];
    }
}
