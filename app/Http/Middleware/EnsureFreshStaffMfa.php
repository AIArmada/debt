<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreshStaffMfa
{
    private const MAX_MARKER_AGE_SECONDS = 900;

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $panel = Filament::getCurrentOrDefaultPanel();

        abort_unless($user instanceof User && $panel instanceof Panel && $user->canAccessPanel($panel), 403);

        if (blank($user->getAppAuthenticationSecret())) {
            if ($request->is('admin/multi-factor-authentication/*')) {
                return $next($request);
            }

            return redirect()->to(Filament::getSetUpRequiredMultiFactorAuthenticationUrl() ?? '/admin/multi-factor-authentication/set-up');
        }

        $verifiedUserId = $request->session()->get('staff_mfa_verified_user_id');
        $verifiedAt = $request->session()->get('staff_mfa_verified_at');

        $markerAge = is_int($verifiedAt) ? now()->getTimestamp() - $verifiedAt : null;

        if (
            $verifiedUserId === (string) $user->getAuthIdentifier()
            && $markerAge !== null
            && $markerAge >= 0
            && $markerAge <= self::MAX_MARKER_AGE_SECONDS
        ) {
            return $next($request);
        }

        $request->session()->forget(['staff_mfa_verified_user_id', 'staff_mfa_verified_at']);

        if ($request->is('admin/*')) {
            $request->session()->put('staff_mfa_intended', $request->getRequestUri());
        }

        return redirect()->route('staff.mfa.challenge');
    }
}
