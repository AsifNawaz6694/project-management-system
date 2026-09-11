<?php

namespace App\Modules\TaskManagement\Policies;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;

/**
 * Single source of truth for task authorisation.
 *
 * Every check combines a capability (does this role hold the permission, and
 * does the project's permission scheme grant it here?) with object scope (is
 * this specific task within the user's visibility?). The audit found write
 * paths enforcing only the first half, which allowed editing a task the same
 * user could not open.
 */
class TaskPolicy
{
    public function __construct(private readonly ProjectPermissionResolver $resolver) {}

    /**
     * Admins bypass every check.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.view') && $this->inScope($user, $task);
    }

    public function create(User $user, ?Project $project = null): bool
    {
        return $this->resolver->allows($user, $project, 'tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.update') && $this->inScope($user, $task);
    }

    /**
     * Status may also be moved by the assignee, or by a member of the owning
     * team, when they hold the narrower update-status permission.
     */
    public function changeStatus(User $user, Task $task): bool
    {
        if (! $this->inScope($user, $task)) {
            return false;
        }

        if ($this->can($user, $task, 'tasks.update')) {
            return true;
        }

        if (! $this->can($user, $task, 'tasks.update-status')) {
            return false;
        }

        return $task->assignee_id === $user->id
            || ($task->team_id && in_array($task->team_id, $user->teamIds(), true));
    }

    /**
     * Taking work off the board — archiving, deleting, restoring — is a
     * manager's call.
     *
     * The permission alone is not enough: a narrower responsibility bundle or a
     * direct grant can carry `tasks.delete`, and removing someone else's task
     * is not something a delivery grant should imply. Super Admin passes
     * through `before()`.
     */
    public function manageLifecycle(User $user): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->manageLifecycle($user)
            && $this->can($user, $task, 'tasks.delete')
            && $this->inScope($user, $task);
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->manageLifecycle($user)
            && $this->can($user, $task, 'tasks.archive')
            && $this->inScope($user, $task);
    }

    /**
     * The trash is the restore surface, so it follows the same rule as delete.
     */
    public function viewTrash(User $user): bool
    {
        return $this->manageLifecycle($user) && $user->hasPermission('tasks.delete');
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.assign') && $this->inScope($user, $task);
    }

    public function link(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.link') && $this->inScope($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->inScope($user, $task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.update') && $this->inScope($user, $task);
    }

    public function logTime(User $user, Task $task): bool
    {
        return $this->can($user, $task, 'tasks.log-time') && $this->inScope($user, $task);
    }

    public function bulkEdit(User $user): bool
    {
        return $user->hasPermission('tasks.bulk-edit');
    }

    /**
     * Capability check that also consults the owning project's permission
     * scheme, passing the task itself so assignee/reporter grants can resolve.
     */
    private function can(User $user, Task $task, string $permission): bool
    {
        return $this->resolver->allows($user, $task->project, $permission, $task);
    }

    /**
     * Is this task inside the user's visibility scope?
     *
     * Delegates to the same scope the list queries use, so read and write can
     * never disagree about what a user is allowed to touch.
     */
    private function inScope(User $user, Task $task): bool
    {
        if ($user->hasPermission('tasks.view-all')) {
            return true;
        }

        return Task::query()
            ->visibleTo($user)
            ->whereKey($task->id)
            ->exists();
    }
}
