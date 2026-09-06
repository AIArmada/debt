<?php

namespace App\Policies;

use App\Domain\Enums\MemberRole;
use App\Models\Record;
use App\Models\User;
use App\Services\ProfileAccess;

class RecordPolicy
{
    public function view(User $user, Record $record): bool
    {
        $record->loadMissing('profile');

        return app(ProfileAccess::class)->can($user, $record->profile, [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ]);
    }

    public function update(User $user, Record $record): bool
    {
        $record->loadMissing('profile');

        return app(ProfileAccess::class)->can($user, $record->profile, [MemberRole::Owner, MemberRole::Editor]);
    }

    public function delete(User $user, Record $record): bool
    {
        return $this->update($user, $record);
    }
}
