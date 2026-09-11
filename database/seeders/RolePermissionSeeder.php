<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use App\Modules\UserManagement\Services\ResponsibilityRegistry;
use Illuminate\Database\Seeder;

/**
 * Permissions and the three workspace roles.
 *
 * Roles stay deliberately few (RBAC §17). Everyone in the organisation is an
 * Employee; Manager is the workspace-wide delivery role held by the DTT
 * managers; Super Admin is the single unrestricted account. Every narrower
 * responsibility is a direct per-user grant — see ResponsibilityRegistry.
 *
 * Idempotent: safe to re-run after editing PermissionRegistry.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissions();

        $admin = Role::updateOrCreate(
            ['slug' => Role::ADMIN],
            [
                'name' => 'Super Admin',
                'description' => 'Unrestricted access to every module, record and setting.',
                'is_system' => true,
            ],
        );

        $manager = Role::updateOrCreate(
            ['slug' => Role::MANAGER],
            [
                'name' => 'Manager',
                'description' => 'Runs delivery across the organisation: projects, tasks, teams and reports.',
                'is_system' => true,
            ],
        );

        $employee = Role::updateOrCreate(
            ['slug' => Role::EMPLOYEE],
            [
                'name' => 'Employee',
                'description' => 'The base role everyone holds. Sees only the work they are involved in.',
                'is_system' => true,
            ],
        );

        // Super Admin holds every permission, including any added later — and
        // is the only role that holds the modules listed in
        // PermissionRegistry::SUPER_ADMIN_MODULES (People & Goals and the
        // administration surface), which the sidebar hides for everyone else.
        $admin->syncPermissionsBySlug(Permission::query()->pluck('slug')->all());

        // Manager is exactly the DTT Manager responsibility: full work
        // management, no security-critical administration.
        $manager->syncPermissionsBySlug(ResponsibilityRegistry::dttManager());

        $employee->syncPermissionsBySlug(self::employeePermissions());
    }

    /**
     * The floor everyone stands on (RBAC §11). Nothing here widens visibility:
     * there is no `*.view-all`, no assign, no delete, no user/role/department
     * administration. Row access comes from the `visibleTo` scopes.
     *
     * Filtered through PermissionRegistry::withoutSuperAdminOnly() like every
     * other non-admin grant, so the People & Goals and administration modules
     * stay with the Super Admin no matter what is listed here.
     *
     * @return array<int, string>
     */
    public static function employeePermissions(): array
    {
        return PermissionRegistry::withoutSuperAdminOnly([
            'projects.view',

            'tasks.view', 'tasks.create', 'tasks.update-status', 'tasks.log-time', 'tasks.link',

            'workflows.view',

            'teams.view',

            'reports.view',

            'meetings.view', 'meetings.create', 'meetings.update',
            'okrs.view', 'okrs.create', 'okrs.update',
            'feedback.view', 'feedback.give',
        ]);
    }

    /**
     * Bring the permissions table in line with the registry, and drop rows for
     * permissions that no longer exist so a removed module cannot leave a
     * dangling grant behind.
     */
    private function syncPermissions(): void
    {
        $slugs = [];

        foreach (PermissionRegistry::flat() as $permission) {
            $slugs[] = $permission['slug'];

            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                ],
            );
        }

        Permission::query()->whereNotIn('slug', $slugs)->delete();
    }
}
