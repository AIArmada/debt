<?php

namespace Database\Factories;

use App\Domain\Enums\MemberRole;
use App\Models\FinancialProfile;
use App\Models\ProfileMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProfileMember> */
class ProfileMemberFactory extends Factory
{
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
