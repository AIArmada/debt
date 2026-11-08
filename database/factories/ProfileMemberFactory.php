<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\ProfileMember;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProfileMember> */
class ProfileMemberFactory extends Factory
{
    protected $model = ProfileMember::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'user_id' => User::factory(),
            'role' => MemberRole::Editor,
            'accepted_at' => now(),
            'revoked_at' => null,
        ];
    }
}
