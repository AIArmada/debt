<?php

namespace Database\Factories;

use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyStatus;
use App\Models\FinancialProfile;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Party> */
class PartyFactory extends Factory
{
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
