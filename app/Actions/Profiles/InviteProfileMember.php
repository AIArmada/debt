<?php

namespace App\Actions\Profiles;

use App\Models\FinancialProfile;
use App\Models\ProfileInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class InviteProfileMember
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @return array{invitation: ProfileInvitation, token: string} */
    public function handle(User $user, FinancialProfile $profile, string $email, string $role): array
    {
        Gate::forUser($user)->authorize('inviteMember', $profile);
        $token = Str::random(64);
        $invitation = $profile->invitations()->create([
            'invited_by_user_id' => $user->getKey(),
            'email' => strtolower(trim($email)),
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $this->auditLogger->record(
            $profile,
            $user,
            ProfileInvitation::class,
            $invitation->getKey(),
            'created',
            after: $invitation->only(['profile_id', 'email', 'role', 'expires_at']),
        );

        return ['invitation' => $invitation, 'token' => $token];
    }
}
