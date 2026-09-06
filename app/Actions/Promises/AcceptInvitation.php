<?php

namespace App\Actions\Promises;

use App\Domain\StringNormalizer;
use App\Models\ProfileInvite;
use App\Models\ProfileMember;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, string $token): ProfileMember
    {
        return DB::transaction(function () use ($user, $token): ProfileMember {
            $invite = ProfileInvite::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invite === null || ! $invite->isUsable()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }

            if (StringNormalizer::lowercase($user->email) !== StringNormalizer::lowercase($invite->email)) {
                throw ValidationException::withMessages(['invitation' => 'Sign in with the invited email address.']);
            }

            $member = ProfileMember::query()
                ->where('profile_id', $invite->profile_id)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();
            if ($member === null) {
                $member = new ProfileMember;
                $member->profile()->associate($invite->profile);
                $member->user()->associate($user);
            }
            $member->forceFill(['role' => $invite->role, 'accepted_at' => now(), 'revoked_at' => null])->save();
            $invite->forceFill(['accepted_at' => now()])->save();
            $this->activityLogger->record($invite->profile, $user, $member, 'member_invitation_accepted', after: [
                'role' => $member->role->value,
            ]);

            return $member->refresh();
        });
    }
}
