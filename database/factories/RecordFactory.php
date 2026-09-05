<?php

namespace Database\Factories;

use App\Models\FinancialProfile;
use App\Models\Record;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'sensitivity' => 'private',
            'is_archived' => false,
        ];
    }
}
