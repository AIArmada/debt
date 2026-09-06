<?php

namespace App\Policies;

use App\Domain\Enums\MemberRole;
use App\Models\Obligation;
use App\Models\User;
use App\Services\ProfileAccess;

class ObligationPolicy
{
    public function view(User $user, Obligation $obligation): bool
    {
        $obligation->loadMissing('record.profile');

        return app(ProfileAccess::class)->can($user, $obligation->record->profile, [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ]);
    }

    public function recordMovement(User $user, Obligation $obligation): bool
    {
        $obligation->loadMissing('record.profile');

        return app(ProfileAccess::class)->can($user, $obligation->record->profile, [MemberRole::Owner, MemberRole::Editor]);
    }
}
