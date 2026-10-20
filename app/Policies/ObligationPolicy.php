<?php

namespace App\Policies;

use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ObligationPolicy
{
    public function view(User $user, Obligation $obligation): bool
    {
        $obligation->loadMissing('record.profile');

        return Gate::forUser($user)->allows('view', $obligation->record);
    }

    public function recordMovement(User $user, Obligation $obligation): bool
    {
        $obligation->loadMissing('record.profile');

        return Gate::forUser($user)->allows('update', $obligation->record);
    }
}
