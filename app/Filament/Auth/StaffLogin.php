<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;

class StaffLogin extends Login
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null) {
            $user = Filament::auth()->user();

            if ($user !== null) {
                session()->put([
                    'staff_mfa_verified_user_id' => (string) $user->getAuthIdentifier(),
                    'staff_mfa_verified_at' => now()->timestamp,
                ]);
            }
        }

        return $response;
    }
}
