<?php

namespace App\Actions\Promises;

use App\Models\ApiToken;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RevokeApiToken
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, FinancialProfile $profile, ApiToken $token): void
    {
        Gate::forUser($user)->authorize('update', $profile);

        if ($token->profile_id !== $profile->getKey()) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($user, $profile, $token): void {
            $lockedToken = ApiToken::query()->whereKey($token->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedToken->profile_id !== $profile->getKey()) {
                throw new AuthorizationException;
            }

            $this->activityLogger->record($profile, $user, $lockedToken, 'api_token_revoked', after: ['name' => $lockedToken->name]);
            $lockedToken->delete();
        });
    }
}
