<?php

namespace Database\Factories;

use App\Features\PromiseTracker\Domain\Enums\ApiTokenAbility;
use App\Features\PromiseTracker\Models\ApiToken;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\User;
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
