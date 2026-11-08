<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\PartyKind;
use App\Features\PromiseTracker\Domain\Enums\PartyStatus;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Party> */
class PartyFactory extends Factory
{
    protected $model = Party::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'kind' => PartyKind::Individual,
            'display_name' => fake()->name(),
            'status' => PartyStatus::Active,
        ];
    }
}
