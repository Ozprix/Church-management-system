<?php

return [
    'two_factor' => [
        'default_enforced_roles' => [
            'admin',
            'tenant_owner',
            'finance_manager',
        ],
        'default_enforced_permissions' => [
            'users.manage_security',
            'rbac.manage',
            'finance.manage_donations',
        ],
        'lock_disable_for_enforced_users' => true,
    ],
];
