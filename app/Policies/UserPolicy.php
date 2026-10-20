<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staffAccess.viewAny');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('staffAccess.viewAny');
    }
}
