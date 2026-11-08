<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\ProfileInvite;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProfileInvite> */
class ProfileInviteFactory extends Factory
{
    protected $model = ProfileInvite::class;

    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => MemberRole::Viewer,
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }
}
