<?php

declare(strict_types=1);

return [
    'database' => [
        'table_prefix' => env('AUTHZ_TABLE_PREFIX', ''),
        'tables' => [
            'roles' => env('AUTHZ_TABLE_ROLES', 'roles'),
            'permissions' => env('AUTHZ_TABLE_PERMISSIONS', 'permissions'),
            'model_has_permissions' => env('AUTHZ_TABLE_MODEL_HAS_PERMISSIONS', 'model_has_permissions'),
            'model_has_roles' => env('AUTHZ_TABLE_MODEL_HAS_ROLES', 'model_has_roles'),
            'role_has_permissions' => env('AUTHZ_TABLE_ROLE_HAS_PERMISSIONS', 'role_has_permissions'),
            'scopes' => env('AUTHZ_TABLE_SCOPES', 'authz_scopes'),
        ],
    ],
    'super_admin_role' => '',
    'guards' => ['web'],
    'users' => [
        'email_column' => 'email',
        'name_column' => 'name',
    ],
    'wildcard_permissions' => false,
    'permissions' => [
        'separator' => env('AUTHZ_PERMISSION_SEPARATOR', '.'),
        'case' => env('AUTHZ_PERMISSION_CASE', 'camel'),
    ],
    'custom_permissions' => [],
    'sync' => [
        'permissions' => [],
        'roles' => [],
    ],
    'scopes' => [
        'enabled' => false,
        'auto_create' => true,
        'enforce' => true,
    ],
    'impersonate' => [
        'guard' => env('AUTHZ_IMPERSONATE_GUARD', 'web'),
    ],
];
