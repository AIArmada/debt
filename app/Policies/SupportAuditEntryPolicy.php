<?php

namespace App\Policies;

use App\Models\SupportAuditEntry;
use App\Models\User;

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
