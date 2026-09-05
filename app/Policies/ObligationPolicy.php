<?php

namespace App\Policies;

use App\Models\Obligation;
use App\Models\User;
use App\Services\ProfileAccess;

class ObligationPolicy
{
    public function view(User $user, Obligation $obligation): bool
    {
        $obligation->loadMissing('record');
        $roles = $obligation->record->sensitivity === 'shared'
            ? ['owner', 'editor', 'payment_manager', 'viewer', 'heir']
            : ['owner', 'editor'];

        return $this->canAccess($user, $obligation, $roles);
    }

    public function recordTransaction(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor', 'payment_manager']);
    }

    public function recordEvent(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor', 'payment_manager']);
    }

    public function update(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    public function uploadDocument(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    public function manageTerms(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    public function manageCommunication(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    public function manageSchedule(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor', 'payment_manager']);
    }

    public function manageAssets(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    public function manageDelivery(User $user, Obligation $obligation): bool
    {
        return $this->canAccess($user, $obligation, ['owner', 'editor']);
    }

    /** @param list<string> $roles */
    private function canAccess(User $user, Obligation $obligation, array $roles): bool
    {
        $obligation->loadMissing('record.profile');
        $record = $obligation->record;
        $profile = $record->profile;

        return ! $profile->is_archived
            && ! $record->is_archived
            && app(ProfileAccess::class)->can($user, $profile, $roles);
    }
}
