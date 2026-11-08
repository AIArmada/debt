<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Domain\Enums\RecordVisibility;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Services\ProfileAccess;

class RecordPolicy
{
    public function view(User $user, Record $record): bool
    {
        $record->loadMissing('profile');

        if (! app(ProfileAccess::class)->can($user, $record->profile, [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ])) {
            return false;
        }

        return $record->visibility === RecordVisibility::Shared
            || $record->created_by === $user->getKey()
            || $record->profile->owner_user_id === $user->getKey();
    }

    public function update(User $user, Record $record): bool
    {
        return $this->view($user, $record)
            && app(ProfileAccess::class)->can($user, $record->profile, [MemberRole::Owner, MemberRole::Editor]);
    }

    public function delete(User $user, Record $record): bool
    {
        return $this->update($user, $record);
    }

    public function createShare(User $user, Record $record): bool
    {
        $record->loadMissing('profile');

        if (! $this->view($user, $record)) {
            return false;
        }

        if ($record->visibility === RecordVisibility::Private) {
            return $record->created_by === $user->getKey()
                || $record->profile->owner_user_id === $user->getKey();
        }

        return app(ProfileAccess::class)->can($user, $record->profile, [MemberRole::Owner, MemberRole::Editor]);
    }
}
