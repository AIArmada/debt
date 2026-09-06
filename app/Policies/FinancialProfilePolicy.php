<?php

namespace App\Policies;

use App\Domain\Enums\MemberRole;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;

class FinancialProfilePolicy
{
    public function view(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, [
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
