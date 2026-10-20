<?php

namespace App\Policies;

use App\Models\SupportCase;
use App\Models\User;

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
