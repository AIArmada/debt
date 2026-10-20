<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffMfaChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user('web');
        $panel = Filament::getPanel('admin');
        abort_unless($user instanceof User && $user->canAccessPanel($panel), 403);

        if (blank($user->getAppAuthenticationSecret())) {
            return redirect()->to('/admin/multi-factor-authentication/set-up');
        }

        return view('staff.mfa-challenge');
    }

    public function verify(Request $request, AppAuthentication $authentication): RedirectResponse
    {
        $user = $request->user('web');
        $panel = Filament::getPanel('admin');
        abort_unless($user instanceof User && $user->canAccessPanel($panel), 403);

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:128'],
            'recovery_code' => ['nullable', 'string', 'max:128'],
        ]);
        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));

        if ($code === '' && $recoveryCode === '') {
            throw ValidationException::withMessages(['code' => 'Enter an authenticator code or a recovery code.']);
        }

        $limiterKey = 'staff-mfa:'.hash('sha256', $user->getAuthIdentifier().'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again later.']);
        }

        RateLimiter::hit($limiterKey, 60);
        $valid = $recoveryCode !== ''
            ? $authentication->verifyRecoveryCode($recoveryCode, $user)
            : $authentication->verifyCode($code, $user->getAppAuthenticationSecret(), shouldPreventCodeReuse: true);

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or has already been used.']);
        }

        RateLimiter::clear($limiterKey);
        $request->session()->put([
            'staff_mfa_verified_user_id' => (string) $user->getAuthIdentifier(),
            'staff_mfa_verified_at' => now()->timestamp,
        ]);

        $intended = $request->session()->pull('staff_mfa_intended', '/admin');

        return redirect()->to(is_string($intended) && str_starts_with($intended, '/admin') ? $intended : '/admin');
    }
}
