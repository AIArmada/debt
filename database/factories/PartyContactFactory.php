<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyContact> */
class PartyContactFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'label' => fake()->randomElement(['mobile', 'office', 'WhatsApp', 'email']),
            'value' => fake()->safeEmail(),
            'is_primary' => false,
            'created_by' => User::factory(),
        ];
    }
}
