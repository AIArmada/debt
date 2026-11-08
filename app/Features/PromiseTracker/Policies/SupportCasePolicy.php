<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;

class SupportCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supportCase.viewAny');
    }

    public function view(User $user, SupportCase $supportCase): bool
    {
        return $user->can('supportCase.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supportCase.create');
    }

    public function update(User $user, SupportCase $supportCase): bool
    {
        return $user->can('supportCase.update');
    }
}
