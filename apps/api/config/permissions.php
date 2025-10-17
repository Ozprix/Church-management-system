<?php

return [
    'super_permission' => 'system.super_admin',

    'modules' => [
        'tenancy' => [
            'feature' => 'tenancy',
            'label' => 'Tenant Management',
            'description' => 'Provision tenants, manage domains, billing plans, and usage.',
            'enabled_by_default' => true,
            'permissions' => [
                'tenancy.view_plans' => [
                    'name' => 'View plans',
                    'description' => 'View available subscription plans and tenant assignments.',
                ],
                'tenancy.manage_onboarding' => [
                    'name' => 'Manage onboarding',
                    'description' => 'Create tenants and manage onboarding workflow.',
                ],
                'tenancy.manage_plans' => [
                    'name' => 'Manage plans',
                    'description' => 'Assign plans and update tenant subscription settings.',
                ],
            ],
        ],
        'members' => [
            'feature' => 'members',
            'label' => 'Members & Families',
            'description' => 'Manage members, families, and related custom data.',
            'enabled_by_default' => true,
            'permissions' => [
                'members.view' => [
                    'name' => 'View members',
                    'description' => 'View member lists and profiles.',
                ],
                'members.manage' => [
                    'name' => 'Manage members',
                    'description' => 'Create, update, and archive members.',
                ],
                'families.manage' => [
                    'name' => 'Manage families',
                    'description' => 'Create and manage family groupings.',
                ],
                'member_custom_fields.manage' => [
                    'name' => 'Manage member custom fields',
                    'description' => 'Create and administer custom member fields.',
                ],
            ],
        ],
        'membership_processes' => [
            'feature' => 'membership_processes',
            'label' => 'Membership Lifecycle',
            'description' => 'Automate member journey stages and lifecycle workflows.',
            'enabled_by_default' => true,
            'permissions' => [
                'membership_processes.manage' => [
                    'name' => 'Manage membership processes',
                    'description' => 'Create and update membership lifecycle processes and stages.',
                ],
                'membership_processes.run' => [
                    'name' => 'Run membership processes',
                    'description' => 'Start and advance members through lifecycle stages.',
                ],
            ],
        ],
        'attendance' => [
            'feature' => 'attendance',
            'label' => 'Attendance & Services',
            'description' => 'Track attendance and configure services.',
            'enabled_by_default' => true,
            'permissions' => [
                'attendance.view' => [
                    'name' => 'View attendance',
                    'description' => 'View attendance records and summaries.',
                ],
                'attendance.manage' => [
                    'name' => 'Manage attendance',
                    'description' => 'Record and update attendance.',
                ],
                'services.manage' => [
                    'name' => 'Manage services',
                    'description' => 'Create and maintain service definitions.',
                ],
                'gatherings.manage' => [
                    'name' => 'Manage gatherings',
                    'description' => 'Schedule and update gatherings.',
                ],
            ],
        ],
        'visitors' => [
            'feature' => 'visitors',
            'label' => 'Visitor Workflows',
            'description' => 'Automate visitor follow-up workflows.',
            'enabled_by_default' => true,
            'permissions' => [
                'visitors.manage_workflows' => [
                    'name' => 'Manage visitor workflows',
                    'description' => 'Create and update visitor workflows and steps.',
                ],
                'visitors.manage_followups' => [
                    'name' => 'Manage visitor follow-ups',
                    'description' => 'Start and track visitor follow-up processes.',
                ],
            ],
        ],
        'volunteers' => [
            'feature' => 'volunteers',
            'label' => 'Volunteer Management',
            'description' => 'Coordinate volunteer roles, assignments, and availability.',
            'enabled_by_default' => true,
            'permissions' => [
                'volunteers.view' => [
                    'name' => 'View volunteer data',
                    'description' => 'View volunteer roles, teams, and assignments.',
                ],
                'volunteers.manage_roles' => [
                    'name' => 'Manage volunteer roles',
                    'description' => 'Create and update volunteer roles.',
                ],
                'volunteers.manage_teams' => [
                    'name' => 'Manage volunteer teams',
                    'description' => 'Create and update volunteer teams.',
                ],
                'volunteers.manage_assignments' => [
                    'name' => 'Manage volunteer assignments',
                    'description' => 'Assign volunteers to gatherings and shifts.',
                ],
                'volunteers.manage_availability' => [
                    'name' => 'Manage volunteer availability',
                    'description' => 'Update volunteer availability preferences.',
                ],
            ],
        ],
        'volunteer_pipeline' => [
            'feature' => 'volunteer_pipeline',
            'label' => 'Volunteer Pipeline',
            'description' => 'Manage volunteer signups, confirmations, and hours tracking.',
            'enabled_by_default' => true,
            'permissions' => [
                'volunteer_pipeline.manage_signups' => [
                    'name' => 'Manage volunteer signups',
                    'description' => 'Review and approve volunteer applications.',
                ],
                'volunteer_pipeline.manage_hours' => [
                    'name' => 'Manage volunteer hours',
                    'description' => 'Log and adjust volunteer service hours.',
                ],
            ],
        ],
        'notifications' => [
            'feature' => 'notifications',
            'label' => 'Notifications & Messaging',
            'description' => 'Send communications and manage templates.',
            'enabled_by_default' => true,
            'permissions' => [
                'notifications.view' => [
                    'name' => 'View notifications',
                    'description' => 'View notification queue and history.',
                ],
                'notifications.manage' => [
                    'name' => 'Send notifications',
                    'description' => 'Queue and cancel notifications.',
                ],
                'notifications.manage_templates' => [
                    'name' => 'Manage notification templates',
                    'description' => 'Create and update notification templates.',
                ],
            ],
        ],
        'notifications_automation' => [
            'feature' => 'notifications_automation',
            'label' => 'Notification Automation',
            'description' => 'Automate notification rules, drip campaigns, and analytics.',
            'enabled_by_default' => true,
            'permissions' => [
                'notifications.rules_manage' => [
                    'name' => 'Manage notification rules',
                    'description' => 'Create and update notification automation rules.',
                ],
                'notifications.rules_run' => [
                    'name' => 'Run notification rules',
                    'description' => 'Trigger notification automation runs and view analytics.',
                ],
            ],
        ],
        'users' => [
            'feature' => 'tenancy',
            'label' => 'User Security',
            'description' => 'Manage user security settings and resets.',
            'enabled_by_default' => true,
            'permissions' => [
                'users.manage_security' => [
                    'name' => 'Manage user security',
                    'description' => 'Reset two-factor authentication and enforce security policies.',
                ],
            ],
        ],
        'finance' => [
            'feature' => 'finance',
            'label' => 'Finance & Giving',
            'description' => 'Track donations, pledges, and recurring giving.',
            'enabled_by_default' => true,
            'permissions' => [
                'finance.view_dashboard' => [
                    'name' => 'View finance dashboard',
                    'description' => 'View finance KPIs and dashboards.',
                ],
                'finance.manage_funds' => [
                    'name' => 'Manage funds',
                    'description' => 'Create and update designated funds.',
                ],
                'finance.manage_donations' => [
                    'name' => 'Manage donations',
                    'description' => 'Record and edit donations.',
                ],
                'finance.manage_payment_methods' => [
                    'name' => 'Manage payment methods',
                    'description' => 'Create and update member payment methods.',
                ],
                'finance.manage_pledges' => [
                    'name' => 'Manage pledges',
                    'description' => 'Create and manage pledge commitments.',
                ],
                'finance.manage_recurring' => [
                    'name' => 'Manage recurring donations',
                    'description' => 'Create and update recurring donation schedules.',
                ],
                'finance.export' => [
                    'name' => 'Export finance data',
                    'description' => 'Export finance reports and donor statements.',
                ],
            ],
        ],
        'reports' => [
            'feature' => 'reports',
            'label' => 'Reporting',
            'description' => 'Generate finance reports and statements.',
            'enabled_by_default' => true,
            'permissions' => [
                'reports.finance_generate' => [
                    'name' => 'Generate finance reports',
                    'description' => 'Queue and download finance PDF reports.',
                ],
            ],
        ],
        'compliance' => [
            'feature' => 'compliance',
            'label' => 'Compliance & Audit',
            'description' => 'Audit logging, retention policies, and privacy workflows.',
            'enabled_by_default' => true,
            'permissions' => [
                'compliance.view_audit_logs' => [
                    'name' => 'View audit logs',
                    'description' => 'Review audit history and compliance reports.',
                ],
                'compliance.manage_retention' => [
                    'name' => 'Manage data retention',
                    'description' => 'Configure retention settings and execute privacy jobs.',
                ],
            ],
        ],
    ],

    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Full access to all modules and settings.',
            'is_default' => true,
            'grants' => ['*'],
        ],
        'tenant_owner' => [
            'name' => 'Tenant Owner',
            'description' => 'Manages onboarding, billing, compliance, and configuration.',
            'grants' => [
                'tenancy.view_plans',
                'tenancy.manage_onboarding',
                'tenancy.manage_plans',
                'compliance.view_audit_logs',
                'compliance.manage_retention',
                'users.manage_security',
            ],
        ],
        'member_manager' => [
            'name' => 'Member Manager',
            'description' => 'Manages members, families, attendance, and visitor workflows.',
            'grants' => [
                'members.view',
                'members.manage',
                'families.manage',
                'member_custom_fields.manage',
                'membership_processes.manage',
                'membership_processes.run',
                'attendance.view',
                'attendance.manage',
                'services.manage',
                'gatherings.manage',
                'visitors.manage_workflows',
                'visitors.manage_followups',
            ],
        ],
        'volunteer_coordinator' => [
            'name' => 'Volunteer Coordinator',
            'description' => 'Coordinates volunteer roles, teams, assignments, and availability.',
            'grants' => [
                'volunteers.view',
                'volunteers.manage_roles',
                'volunteers.manage_teams',
                'volunteers.manage_assignments',
                'volunteers.manage_availability',
                'volunteer_pipeline.manage_signups',
                'volunteer_pipeline.manage_hours',
                'attendance.view',
            ],
        ],
        'finance_manager' => [
            'name' => 'Finance Manager',
            'description' => 'Manages donations, pledges, funds, and revenue reports.',
            'grants' => [
                'finance.view_dashboard',
                'finance.manage_funds',
                'finance.manage_donations',
                'finance.manage_payment_methods',
                'finance.manage_pledges',
                'finance.manage_recurring',
                'finance.export',
                'reports.finance_generate',
            ],
        ],
        'communications_manager' => [
            'name' => 'Communications Manager',
            'description' => 'Handles notifications and templates.',
            'grants' => [
                'notifications.view',
                'notifications.manage',
                'notifications.manage_templates',
                'notifications.rules_manage',
                'notifications.rules_run',
            ],
        ],
    ],
];
