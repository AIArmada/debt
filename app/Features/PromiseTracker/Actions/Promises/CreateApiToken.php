<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Actions\Promises\Data\CreateApiTokenData;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\ApiToken;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\ActivityLogger;
use App\Features\PromiseTracker\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateApiToken
{
    public function __construct(
        private readonly ProfileAccess $profileAccess,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, FinancialProfile $profile, CreateApiTokenData $data): ApiToken
    {
        if (! $this->profileAccess->can($user, $profile, [MemberRole::Owner])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $profile, $data): ApiToken {
            $token = Str::random(64);
            $apiToken = ApiToken::query()->create([
                'profile_id' => $profile->getKey(),
                'user_id' => $user->getKey(),
                'name' => $data->name,
                'token_hash' => hash('sha256', $token),
                'abilities' => $data->abilities,
            ]);
            $apiToken->setAttribute('plain_token', $token);
            $this->activityLogger->record($profile, $user, $apiToken, 'api_token_created', after: ['name' => $apiToken->name, 'abilities' => $data->abilities]);

            return $apiToken;
        });
    }
}
