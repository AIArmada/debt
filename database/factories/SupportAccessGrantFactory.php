<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\SupportAccessGrantStatus;
use App\Features\PromiseTracker\Models\SupportAccessGrant;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAccessGrant>
 */
class SupportAccessGrantFactory extends Factory
{
    protected $model = SupportAccessGrant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => SupportCase::factory(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'status' => SupportAccessGrantStatus::Pending,
            'starts_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_by' => null,
        ];
    }
}
