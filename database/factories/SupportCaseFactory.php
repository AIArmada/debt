<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\SupportCaseStatus;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportCase>
 */
class SupportCaseFactory extends Factory
{
    protected $model = SupportCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'opened_by' => User::factory(),
            'subject' => fake()->sentence(5),
            'reason' => fake()->paragraph(),
            'status' => SupportCaseStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ];
    }
}
