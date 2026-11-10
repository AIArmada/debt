<?php

namespace App\Features\PromiseTracker\EntryPoints\Http\Controllers;

use App\Features\PromiseTracker\Actions\Profiles\AcceptInvitation;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class AcceptInvitationController
{
    public function __invoke(string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $member = $acceptInvitation->handle($user, $token);

        return redirect()->route('promises.index', ['profile' => $member->profile_id]);
    }
}
