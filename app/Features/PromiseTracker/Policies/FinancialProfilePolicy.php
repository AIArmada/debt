<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Services\ProfileAccess;

class FinancialProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('profile.lookup');
    }

    public function viewSummary(User $user, FinancialProfile $profile): bool
    {
        return $user->can('profile.viewSummary');
    }

    public function revokeInvite(User $user, FinancialProfile $profile, SupportCase $case): bool
    {
        return $user->can('profileInvite.revoke')
            && $case->profile_id === $profile->getKey()
            && $case->activeGrant() !== null;
    }

    public function view(User $user, FinancialProfile $profile): bool
    {
        return $user->can('profile.viewSummary') || app(ProfileAccess::class)->can($user, $profile, [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ]);
    }

    public function update(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, [MemberRole::Owner]);
    }

    public function createObligation(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, [MemberRole::Owner, MemberRole::Editor]);
    }

    public function manageParties(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, [MemberRole::Owner, MemberRole::Editor]);
    }

    public function manageMembers(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, [MemberRole::Owner]);
    }
}
