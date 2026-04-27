<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::flat() as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                ],
            );
        }

        $admin = Role::updateOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Administrator', 'description' => 'Full system access across every module.', 'is_system' => true],
        );

        $manager = Role::updateOrCreate(
            ['slug' => Role::MANAGER],
            ['name' => 'Manager', 'description' => 'Leads teams, manages projects and tasks.', 'is_system' => true],
        );

        $employee = Role::updateOrCreate(
            ['slug' => Role::EMPLOYEE],
            ['name' => 'Employee', 'description' => 'Works on assigned projects and tasks.', 'is_system' => true],
        );

        $admin->syncPermissionsBySlug(Permission::pluck('slug')->all());

        $manager->syncPermissionsBySlug([
            'users.view',
            'roles.view',
            'projects.view', 'projects.create', 'projects.update', 'projects.manage-members',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.update-status',
            'teams.view', 'teams.manage',
            'reports.view', 'reports.export',
            'settings.view',
        ]);

        $employee->syncPermissionsBySlug([
            'projects.view',
            'tasks.view', 'tasks.update-status',
            'teams.view',
        ]);
    }
}
