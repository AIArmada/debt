<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\PartyContact;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartyContact> */
class PartyContactFactory extends Factory
{
    protected $model = PartyContact::class;

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
