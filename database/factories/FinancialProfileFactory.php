<?php

namespace Database\Factories;

use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialProfile>
 */
class FinancialProfileFactory extends Factory
{
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
            'type' => 'personal',
            'base_currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en-MY',
            'is_islamic_mode_enabled' => false,
            'is_archived' => false,
        ];
    }
}
