<?php

namespace App\Policies;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;

class SupportAccessGrantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supportAccess.request')
            || $user->can('supportAccess.approve')
            || $user->can('supportAccess.revoke');
    }

    public function view(User $user, SupportAccessGrant $supportAccessGrant): bool
    {
        return $this->viewAny($user);
    }

    public function request(User $user, SupportCase $supportCase): bool
    {
        return $user->can('supportAccess.request') && $supportCase->isOpen();
    }

    public function approve(User $user, SupportAccessGrant $supportAccessGrant): bool
    {
        return $user->can('supportAccess.approve')
            && $supportAccessGrant->status === SupportAccessGrantStatus::Pending
            && $supportAccessGrant->requested_by !== $user->getKey()
            && $supportAccessGrant->supportCase->isOpen();
    }

    public function revoke(User $user, SupportAccessGrant $supportAccessGrant): bool
    {
        return $user->can('supportAccess.revoke')
            && in_array($supportAccessGrant->status, [SupportAccessGrantStatus::Approved, SupportAccessGrantStatus::Pending], true);
    }
}
