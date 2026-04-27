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
                    'roles.update' => 'Manage role permissions',
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
