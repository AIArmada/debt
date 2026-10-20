<?php

namespace Database\Seeders;

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Permission as PermissionContract;

class StaffRolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolePermissions = [
            'support_agent' => [
                'profile.lookup', 'profile.viewSummary', 'supportCase.viewAny', 'supportCase.view',
                'supportCase.create', 'supportCase.update', 'supportAccess.request',
                'profileRecord.view', 'profileMember.view', 'profileInvite.revoke',
            ],
            'support_lead' => [
                'profile.lookup', 'profile.viewSummary', 'supportCase.viewAny', 'supportCase.view',
                'supportCase.create', 'supportCase.update', 'supportAccess.request',
                'profileRecord.view', 'profileMember.view', 'profileInvite.revoke',
                'supportAccess.approve', 'supportAccess.revoke', 'supportAudit.viewAny',
            ],
            'support_auditor' => ['supportAudit.viewAny'],
            'security_admin' => ['staffAccess.viewAny', 'staffAccess.assign'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $permissions = array_map(
                static fn (string $permissionName): PermissionContract => Permission::findOrCreate($permissionName, 'web'),
                $permissionNames,
            );

            $role->syncPermissions($permissions);
        }

        if (app()->environment('local', 'testing')) {
            $user = User::query()->where('email', 'test@example.com')->first();

            if ($user !== null) {
                $user->syncRoles(['support_lead', 'security_admin']);
            }
        }
    }
}
