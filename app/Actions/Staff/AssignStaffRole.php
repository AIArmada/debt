<?php

namespace App\Actions\Staff;

use AIArmada\Authz\Models\Role;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignStaffRole
{
    public function handle(User $actor, User $target, string $roleName): void
    {
        Gate::forUser($actor)->authorize('staffAccess.assign');

        if (! in_array($roleName, User::STAFF_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Only seeded staff roles can be assigned.']);
        }

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            throw ValidationException::withMessages(['role' => 'The selected staff role has not been seeded.']);
        }

        DB::transaction(function () use ($actor, $roleName, $role, $target): void {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $lockedTarget->assignRole($role);
            app(SupportAuditLogger::class)->record($actor, 'staff.role.assigned', subject: $lockedTarget, metadata: ['result' => $roleName]);
        });
    }
}
