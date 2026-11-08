<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\ObligationShare;
use App\Features\PromiseTracker\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObligationShare>
 */
class ObligationShareFactory extends Factory
{
    protected $model = ObligationShare::class;

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
