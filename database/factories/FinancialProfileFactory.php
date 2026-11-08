<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialProfile>
 */
class FinancialProfileFactory extends Factory
{
    protected $model = FinancialProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => fake()->company().' financial profile',
            'base_currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
            'is_archived' => false,
            'interest_free_mode' => true,
            'show_hijri' => false,
        ];
    }
}
