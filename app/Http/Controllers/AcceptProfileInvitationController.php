<?php

namespace App\Http\Controllers;

use App\Models\ProfileInvitation;
use App\Services\ActivityNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcceptProfileInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, ActivityNotifier $activityNotifier): RedirectResponse
    {
        $invitation = ProfileInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        if (! hash_equals(strtolower($invitation->email), strtolower((string) $request->user()->email))) {
            abort(403);
        }

        DB::transaction(function () use ($request, $invitation): void {
            $invitation->profile->members()->updateOrCreate(
                ['user_id' => $request->user()->getKey()],
                ['role' => $invitation->role, 'accepted_at' => now(), 'revoked_at' => null],
            );
            if ($invitation->role === 'heir') {
                $invitation->profile->emergencyAccessRequests()->firstOrCreate(
                    ['user_id' => $request->user()->getKey(), 'status' => 'pending'],
                    ['reason' => 'Emergency representative invitation accepted.', 'activate_after' => now()->addDays(7)],
                );
            }
            $invitation->update(['accepted_at' => now()]);
        });

        $activityNotifier->notifyProfileActivity($invitation->profile, 'profile_access_granted', 'Shared profile access updated', 'A collaborator has joined a shared financial profile.', context: ['role' => $invitation->role]);

        $message = $invitation->role === 'heir'
            ? 'The emergency representative invitation was accepted and is waiting for owner activation.'
            : 'You now have access to the financial profile.';

        return redirect()->route('financial-profiles.index')->with('invitation-accepted', $message);
    }
}
