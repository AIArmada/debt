<?php

namespace App\Policies;

use App\Domain\Enums\MemberRole;
use App\Models\Party;
use App\Models\User;
use App\Services\ProfileAccess;

final class PartyPolicy
{
    public function view(User $user, Party $party): bool
    {
        $party->loadMissing('profile');

        return app(ProfileAccess::class)->can($user, $party->profile, [MemberRole::Owner, MemberRole::Editor, MemberRole::Viewer]);
    }

    public function update(User $user, Party $party): bool
    {
        $party->loadMissing('profile');

        return app(ProfileAccess::class)->can($user, $party->profile, [MemberRole::Owner, MemberRole::Editor]);
    }

    public function manageContacts(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }

    public function managePaymentDestinations(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }

    public function revealPaymentDestination(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }

    public function manageRelationships(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }
}
