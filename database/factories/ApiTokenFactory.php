<?php

namespace Database\Factories;

use App\Domain\Enums\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiToken> */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        return [
            'profile_id' => FinancialProfile::factory(),
            'user_id' => User::factory(),
            'name' => 'Test token',
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'abilities' => [ApiTokenAbility::Read->value, ApiTokenAbility::Write->value],
            'last_used_at' => null,
        ];
    }
}
