<?php

namespace App\Features\PromiseTracker\Persistence;

use App\Features\PromiseTracker\Models\User;

final class SaveAppAuthenticationSecret
{
    public function handle(User $user, #[\SensitiveParameter] ?string $secret): void
    {
        $user->forceFill(['app_authentication_secret' => $secret])->save();

        if (request()->hasSession()) {
            if ($secret === null) {
                session()->forget(['staff_mfa_verified_user_id', 'staff_mfa_verified_at']);
            } else {
                session()->put([
                    'staff_mfa_verified_user_id' => (string) $user->getAuthIdentifier(),
                    'staff_mfa_verified_at' => now()->timestamp,
                ]);
            }
        }
    }
}
