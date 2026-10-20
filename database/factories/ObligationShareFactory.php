<?php

namespace Database\Factories;

use App\Models\Obligation;
use App\Models\ObligationShare;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObligationShare>
 */
class ObligationShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obligation_id' => Obligation::factory(),
            'party_id' => Party::factory(),
            'share_bps' => 10000,
        ];
    }
}
