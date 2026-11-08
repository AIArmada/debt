<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;
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
