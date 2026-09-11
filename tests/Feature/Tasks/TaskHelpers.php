<?php

namespace Tests\Feature\Tasks;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStatus;
use Database\Seeders\PermissionSchemeSeeder;
use Database\Seeders\WorkflowSeeder;

/**
 * Shared setup for the task feature tests.
 */
class TaskHelpers
{
    /**
     * Seed permissions, roles and the default workflow.
     */
    public static function bootstrap(): void
    {
        foreach (PermissionRegistry::flat() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name'], 'module' => $permission['module']],
            );
        }

        (new WorkflowSeeder)->run();
        (new PermissionSchemeSeeder)->run();
    }

    /**
     * A user holding exactly the given permission slugs.
     *
     * @param  array<int, string>  $permissions
     */
    public static function userWith(array $permissions, string $roleSlug = 'tester'): User
    {
        $user = User::factory()->create(['status' => 'active']);

        $role = Role::query()->create([
            'slug' => $roleSlug.'-'.uniqid(),
            'name' => 'Tester',
            'description' => 'Test role',
            'is_system' => false,
        ]);

        $ids = Permission::query()->whereIn('slug', $permissions)->pluck('id');
        $role->permissions()->sync($ids);

        $user->roles()->attach($role->id);

        return $user->fresh(['roles.permissions']);
    }

    /**
     * A manager holding exactly the given permissions.
     *
     * Archiving, deleting and restoring are a manager's call
     * (TaskPolicy::manageLifecycle), so exercising those needs the role as
     * well as the permission.
     *
     * @param  array<int, string>  $permissions
     */
    public static function managerWith(array $permissions): User
    {
        $user = static::userWith($permissions);

        $manager = Role::query()->firstOrCreate(
            ['slug' => Role::MANAGER],
            ['name' => 'Manager', 'description' => 'Delivery management', 'is_system' => true],
        );

        $user->roles()->attach($manager->id);

        return $user->fresh(['roles.permissions']);
    }

    public static function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);

        $role = Role::query()->firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Administrator', 'description' => 'Full access', 'is_system' => true],
        );

        $user->roles()->attach($role->id);

        return $user->fresh(['roles.permissions']);
    }

    /**
     * A project on a deliberately unrestricted workflow, for tests that exercise
     * generic behaviour rather than the delivery pipeline.
     */
    public static function project(User $owner): Project
    {
        return Project::factory()->create([
            'owner_id' => $owner->id,
            'workflow_id' => static::openWorkflow()->id,
        ]);
    }

    /**
     * todo / in_progress / done, with no transition rules — anything may move
     * to anything.
     */
    public static function openWorkflow(): Workflow
    {
        $workflow = Workflow::query()->firstOrCreate(
            ['name' => 'Test board'],
            ['description' => 'Unrestricted board used by the test suite.', 'is_default' => false, 'is_system' => false],
        );

        if ($workflow->statuses()->count() === 0) {
            foreach ([
                ['key' => 'todo', 'name' => 'To do', 'category' => 'todo', 'color' => 'slate', 'is_initial' => true],
                ['key' => 'in_progress', 'name' => 'In progress', 'category' => 'in_progress', 'color' => 'amber'],
                ['key' => 'done', 'name' => 'Done', 'category' => 'done', 'color' => 'emerald'],
            ] as $i => $row) {
                $workflow->statuses()->create($row + ['position' => $i + 1]);
            }
        }

        return $workflow->fresh('statuses');
    }

    /**
     * A project on the restricted "Software delivery" workflow.
     */
    public static function softwareProject(User $owner): Project
    {
        return Project::factory()->create([
            'owner_id' => $owner->id,
            'workflow_id' => Workflow::query()->where('name', 'Software delivery')->value('id'),
        ]);
    }

    public static function statusKeys(string $workflowName): array
    {
        $id = Workflow::query()->where('name', $workflowName)->value('id');

        return WorkflowStatus::query()->where('workflow_id', $id)->pluck('key')->all();
    }
}
