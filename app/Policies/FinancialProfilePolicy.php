<?php

namespace App\Policies;

use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\ProfileAccess;

class FinancialProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor', 'payment_manager', 'viewer', 'heir']);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FinancialProfile $profile): bool
    {
        return $this->belongsToUser($user, $profile);
    }

    public function manageBudget(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor']);
    }

    public function createObligation(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor']);
    }

    public function manageParties(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor']);
    }

    public function manageImports(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor']);
    }

    public function viewIntegrations(User $user, FinancialProfile $profile): bool
    {
        return app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor', 'payment_manager', 'viewer']);
    }

    public function manageIntegrations(User $user, FinancialProfile $profile): bool
    {
        return $this->belongsToUser($user, $profile);
    }

    public function inviteMember(User $user, FinancialProfile $profile): bool
    {
        return $this->belongsToUser($user, $profile);
    }

    private function belongsToUser(User $user, FinancialProfile $profile): bool
    {
        return $profile->owner_user_id === $user->getKey() && ! $profile->is_archived;
    }
}
