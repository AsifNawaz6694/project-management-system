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
            'projects' => [
                'label' => 'Projects',
                'permissions' => [
                    'projects.view' => 'View projects',
                    'projects.create' => 'Create projects',
                    'projects.update' => 'Edit projects',
                    'projects.delete' => 'Delete projects',
                    'projects.manage-members' => 'Manage project members',
                ],
            ],
            'tasks' => [
                'label' => 'Tasks',
                'permissions' => [
                    'tasks.view' => 'View tasks',
                    'tasks.create' => 'Create tasks',
                    'tasks.update' => 'Edit tasks',
                    'tasks.delete' => 'Delete tasks',
                    'tasks.assign' => 'Assign tasks to users',
                    'tasks.update-status' => 'Update task status',
                    'tasks.log-time' => 'Log time on tasks',
                ],
            ],
            'teams' => [
                'label' => 'Teams',
                'permissions' => [
                    'teams.view' => 'View teams',
                    'teams.manage' => 'Manage teams',
                ],
            ],
            'expenses' => [
                'label' => 'Expenses',
                'permissions' => [
                    'expenses.view' => 'View expenses',
                    'expenses.create' => 'Submit expenses',
                    'expenses.approve' => 'Approve / reject expenses',
                    'expenses.delete' => 'Delete expenses',
                ],
            ],
            'reports' => [
                'label' => 'Reports & Analytics',
                'permissions' => [
                    'reports.view' => 'View reports',
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
