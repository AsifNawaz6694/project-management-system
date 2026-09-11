<?php

namespace App\Modules\ProjectManagement\Policies;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;

/**
 * Project authorisation: workspace capability, narrowed by the project's
 * permission scheme, and bounded by the same visibility scope the list
 * queries use.
 */
class ProjectPolicy
{
    public function __construct(private readonly ProjectPermissionResolver $resolver) {}

    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view') && $this->inScope($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->resolver->allows($user, $project, 'projects.update') && $this->inScope($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->resolver->allows($user, $project, 'projects.delete') && $this->inScope($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->resolver->allows($user, $project, 'projects.manage-members') && $this->inScope($user, $project);
    }

    private function inScope(User $user, Project $project): bool
    {
        if ($user->hasPermission('projects.view-all')) {
            return true;
        }

        return Project::query()
            ->visibleTo($user)
            ->whereKey($project->id)
            ->exists();
    }
}
