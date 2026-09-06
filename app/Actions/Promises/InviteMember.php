<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\InviteMemberData;
use App\Domain\Enums\MemberRole;
use App\Models\FinancialProfile;
use App\Models\ProfileInvite;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteMember
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, FinancialProfile $profile, InviteMemberData $data): ProfileInvite
    {
        Gate::forUser($user)->authorize('manageMembers', $profile);

        if ($data->role === MemberRole::Owner) {
            throw ValidationException::withMessages(['role' => 'Invitations can only grant editor or viewer access.']);
        }

        return DB::transaction(function () use ($user, $profile, $data): ProfileInvite {
            $token = Str::random(64);
            $invite = ProfileInvite::query()->create([
                'profile_id' => $profile->getKey(),
                'email' => $data->email,
                'role' => $data->role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $user->getKey(),
                'expires_at' => now()->addDays(7),
            ]);
            $invite->setAttribute('token', $token);
            $this->activityLogger->record($profile, $user, $invite, 'member_invited', after: [
                'email' => $data->email,
                'role' => $data->role->value,
            ]);

            return $invite;
        });
    }
}
