<?php

namespace App\Policies;

use App\Domain\Enums\MemberRole;
use App\Domain\Enums\RecordVisibility;
use App\Models\Record;
use App\Models\User;
use App\Services\ProfileAccess;

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
