<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAccessRequest;
use App\Models\ProfileInvitation;
use App\Models\ProfileMember;
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
            $profile = $invitation->profile;
            $member = $profile->members()->where('user_id', $request->user()->getKey())->first() ?? new ProfileMember;
            $member->setAttribute('user_id', $request->user()->getKey());
            $member->fill(['role' => $invitation->role, 'accepted_at' => now(), 'revoked_at' => null]);
            $profile->members()->save($member);

            if ($invitation->role === 'heir') {
                $accessRequest = $profile->emergencyAccessRequests()
                    ->where('user_id', $request->user()->getKey())
                    ->where('status', 'pending')
                    ->first();

                if ($accessRequest === null) {
                    $accessRequest = new EmergencyAccessRequest;
                    $accessRequest->setAttribute('user_id', $request->user()->getKey());
                    $accessRequest->setAttribute('status', 'pending');
                    $accessRequest->setAttribute('reason', 'Emergency representative invitation accepted.');
                    $accessRequest->setAttribute('activate_after', now()->addDays(7));
                    $profile->emergencyAccessRequests()->save($accessRequest);
                }
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
