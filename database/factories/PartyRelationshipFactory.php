<?php

namespace Database\Factories;

use App\Domain\Enums\PartyRelationshipKind;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\PartyRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyRelationship> */
class PartyRelationshipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'from_party_id' => fn (array $attributes): string => Party::factory()->create([
                'profile_id' => $attributes['profile_id'],
            ])->getKey(),
            'to_party_id' => fn (array $attributes): string => Party::factory()->create([
                'profile_id' => $attributes['profile_id'],
            ])->getKey(),
            'kind' => PartyRelationshipKind::AssistantOf,
            'created_by' => User::factory(),
        ];
    }
}
