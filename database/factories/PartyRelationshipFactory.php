<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\PartyRelationshipKind;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\PartyRelationship;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyRelationship> */
class PartyRelationshipFactory extends Factory
{
    protected $model = PartyRelationship::class;

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
