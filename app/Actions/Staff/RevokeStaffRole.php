<?php

namespace App\Actions\Staff;

use AIArmada\Authz\Models\Role;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokeStaffRole
{
    public function handle(User $actor, User $target, string $roleName): void
    {
        Gate::forUser($actor)->authorize('staffAccess.assign');

        if (! in_array($roleName, User::STAFF_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Only seeded staff roles can be revoked.']);
        }

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            throw ValidationException::withMessages(['role' => 'The user does not have the selected seeded staff role.']);
        }

        DB::transaction(function () use ($actor, $roleName, $role, $target): void {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedTarget->hasRole($role, 'web')) {
                throw ValidationException::withMessages(['role' => 'The user does not have the selected seeded staff role.']);
            }

            $lockedTarget->removeRole($role);
            app(SupportAuditLogger::class)->record($actor, 'staff.role.revoked', subject: $lockedTarget, metadata: ['result' => $roleName]);
        });
    }
}
