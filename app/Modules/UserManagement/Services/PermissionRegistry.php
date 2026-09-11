<?php

namespace App\Modules\UserManagement\Services;

class PermissionRegistry
{
    /**
     * Modules and their permissions for this project management tool.
     * Adding a new module = adding an entry here, then re-running the seeder.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'users' => [
                'label' => 'User Management',
                'permissions' => [
                    'users.view' => 'View users',
                    'users.create' => 'Create users',
                    'users.update' => 'Edit users',
                    'users.delete' => 'Delete users',
                    'users.assign-roles' => 'Assign roles to users',
                    'users.assign-permissions' => 'Grant permissions directly to a user',
                ],
            ],
            'departments' => [
                'label' => 'Departments',
                'permissions' => [
                    'departments.view' => 'View departments',
                    'departments.create' => 'Create departments',
                    'departments.update' => 'Edit departments',
                    'departments.delete' => 'Delete departments',
                ],
            ],
            'roles' => [
                'label' => 'Roles & Permissions',
                'permissions' => [
                    'roles.view' => 'View roles',
                    'roles.create' => 'Create custom roles',
                    'roles.update' => 'Manage role permissions',
                    'roles.delete' => 'Delete custom roles',
                ],
            ],
            'permission-schemes' => [
                'label' => 'Permission Schemes',
                'permissions' => [
                    'permission-schemes.view' => 'View permission schemes',
                    'permission-schemes.manage' => 'Create and edit permission schemes',
                ],
            ],
            'projects' => [
                'label' => 'Projects',
                'permissions' => [
                    'projects.view' => 'View projects',
                    'projects.create' => 'Create projects',
                    'projects.update' => 'Edit projects',
                    'projects.delete' => 'Delete projects',
                    'projects.manage-members' => 'Manage project members',
                    'projects.view-all' => 'View every project in the workspace',
                ],
            ],
            'tasks' => [
                'label' => 'Tasks',
                'permissions' => [
                    'tasks.view' => 'View tasks',
                    'tasks.view-all' => 'View every task in the workspace',
                    'tasks.create' => 'Create tasks',
                    'tasks.update' => 'Edit tasks',
                    'tasks.delete' => 'Delete tasks',
                    'tasks.archive' => 'Archive and restore tasks',
                    'tasks.assign' => 'Assign tasks to users and teams',
                    'tasks.update-status' => 'Update task status',
                    'tasks.log-time' => 'Log time on tasks',
                    'tasks.link' => 'Link tasks and manage dependencies',
                    'tasks.bulk-edit' => 'Perform bulk task operations',
                    'tasks.manage-labels' => 'Create and manage labels',
                ],
            ],
            'automations' => [
                'label' => 'Automation',
                'permissions' => [
                    'automations.view' => 'View automation rules',
                    'automations.manage' => 'Create and edit automation rules',
                ],
            ],
            'workflows' => [
                'label' => 'Workflows',
                'permissions' => [
                    'workflows.view' => 'View workflows',
                    'workflows.manage' => 'Create and edit workflows and statuses',
                ],
            ],
            'teams' => [
                'label' => 'Teams',
                'permissions' => [
                    'teams.view' => 'View teams',
                    'teams.manage' => 'Manage teams',
                ],
            ],
            'reports' => [
                'label' => 'Reports & Analytics',
                'permissions' => [
                    'reports.view' => 'View reports',
                    'reports.view-all' => 'View organisation-wide reports',
                    'reports.export' => 'Export reports',
                ],
            ],
            'settings' => [
                'label' => 'Workspace Settings',
                'permissions' => [
                    'settings.view' => 'View workspace settings',
                    'settings.update' => 'Update workspace settings',
                ],
            ],
            'meetings' => [
                'label' => 'Meetings',
                'permissions' => [
                    'meetings.view' => 'View meetings',
                    'meetings.create' => 'Schedule meetings',
                    'meetings.update' => 'Edit own meetings',
                    'meetings.delete' => 'Delete / cancel meetings',
                    'meetings.manage' => 'Manage all meetings & templates',
                ],
            ],
            'okrs' => [
                'label' => 'OKRs & Goals',
                'permissions' => [
                    'okrs.view' => 'View objectives',
                    'okrs.create' => 'Create objectives',
                    'okrs.update' => 'Update objectives & key results',
                    'okrs.delete' => 'Delete objectives',
                    'okrs.manage' => 'Manage company-wide OKRs',
                ],
            ],
            'feedback' => [
                'label' => 'Feedback',
                'permissions' => [
                    'feedback.view' => 'View feedback cycles',
                    'feedback.give' => 'Submit feedback when invited',
                    'feedback.manage' => 'Create and manage feedback cycles',
                ],
            ],
        ];
    }

    /**
     * Modules whose permissions are reserved for the Super Admin.
     *
     * These are the two surfaces nobody else may reach: the People & Goals
     * side of the workspace (meetings, OKRs, feedback) and administration
     * (users, departments, roles, schemes, workflows, automation, settings).
     * The sidebar hides both sections for anyone but the Super Admin
     * (`lib/navigation.ts`); this list is the matching server-side half, so a
     * seeded role cannot hand the routes out behind the hidden nav.
     *
     * @var array<int, string>
     */
    public const SUPER_ADMIN_MODULES = [
        'users',
        'departments',
        'roles',
        'permission-schemes',
        'automations',
        'workflows',
        'settings',
        'meetings',
        'okrs',
        'feedback',
    ];

    /**
     * Every permission slug reserved for the Super Admin.
     *
     * @return array<int, string>
     */
    public static function superAdminOnly(): array
    {
        $slugs = [];

        foreach (self::modules() as $module => $config) {
            if (in_array($module, self::SUPER_ADMIN_MODULES, true)) {
                $slugs = array_merge($slugs, array_keys($config['permissions']));
            }
        }

        return $slugs;
    }

    /**
     * Strip the reserved permissions out of a grant list. Every non-admin
     * grant — role or responsibility bundle — goes through this, so adding a
     * module to SUPER_ADMIN_MODULES is enough to lock it down everywhere.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    public static function withoutSuperAdminOnly(array $slugs): array
    {
        return array_values(array_diff($slugs, self::superAdminOnly()));
    }

    /**
     * @return array<int, array{slug: string, name: string, module: string}>
     */
    public static function flat(): array
    {
        $flat = [];
        foreach (self::modules() as $module => $config) {
            foreach ($config['permissions'] as $slug => $label) {
                $flat[] = [
                    'slug' => $slug,
                    'name' => $label,
                    'module' => $module,
                ];
            }
        }

        return $flat;
    }
}
