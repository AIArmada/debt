<?php

namespace App\Policies;

use App\Models\Record;
use App\Models\User;
use App\Services\ProfileAccess;

class RecordPolicy
{
    public function view(User $user, Record $record): bool
    {
        $record->loadMissing('profile');
        $roles = $record->sensitivity === 'shared'
            ? ['owner', 'editor', 'payment_manager', 'viewer', 'heir']
            : ['owner', 'editor'];

        return $this->canAccess($user, $record, $roles);
    }

    public function create(User $user, Record $record): bool
    {
        return app(ProfileAccess::class)->can($user, $record->profile, ['owner', 'editor']);
    }

    public function update(User $user, Record $record): bool
    {
        return $this->canAccess($user, $record, ['owner', 'editor']);
    }

    /** @param list<string> $roles */
    private function canAccess(User $user, Record $record, array $roles): bool
    {
        $record->loadMissing('profile');
        $profile = $record->profile;

        return ! $profile->is_archived
            && ! $record->is_archived
            && app(ProfileAccess::class)->can($user, $profile, $roles);
    }
}
