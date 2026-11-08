<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Models\SupportAuditEntry;
use App\Features\PromiseTracker\Models\User;

class SupportAuditEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supportAudit.viewAny');
    }

    public function view(User $user, SupportAuditEntry $supportAuditEntry): bool
    {
        return $user->can('supportAudit.viewAny');
    }
}
