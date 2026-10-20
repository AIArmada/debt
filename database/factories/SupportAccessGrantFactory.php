<?php

namespace Database\Factories;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAccessGrant>
 */
class SupportAccessGrantFactory extends Factory
{
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
