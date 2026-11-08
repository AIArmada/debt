<?php

namespace App\Features\PromiseTracker\Actions\Auth;

use App\Features\PromiseTracker\Models\ApiToken;

final class TouchApiToken
{
    public function handle(ApiToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }
}
