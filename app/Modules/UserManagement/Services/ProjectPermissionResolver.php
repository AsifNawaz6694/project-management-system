<?php

namespace App\Modules\UserManagement\Services;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\UserManagement\Models\PermissionSchemeGrant as Grant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Answers "may this user do X inside this project?".
 *
 * Two gates, in order:
 *
 *   1. The workspace role must hold the permission at all. A scheme can never
 *      hand out a capability the user's role does not have — schemes narrow,
 *      they do not escalate.
 *   2. At least one grant in the project's scheme must match the user.
 *
 * A permission with no grants in the scheme is *ungoverned* and passes on the
 * strength of gate 1 alone, which is what keeps schemes additive: an admin only
 * writes rules for the permissions they actually want to restrict.
 */
class ProjectPermissionResolver
{
    /**
     * Permissions that mean something inside a single project. Anything outside
     * this list is workspace-wide and never consults a scheme.
     *
     * @var array<int, string>
     */
    public const GOVERNED = [
        'projects.update',
        'projects.delete',
        'projects.manage-members',
        'tasks.view',
        'tasks.create',
        'tasks.update',
        'tasks.delete',
        'tasks.archive',
        'tasks.assign',
        'tasks.update-status',
        'tasks.log-time',
        'tasks.link',
        'tasks.bulk-edit',
        'reports.export',
    ];

    /** Project roles recognised on the project_user pivot. */
    public const PROJECT_ROLES = ['owner', 'lead', 'member', 'viewer'];

    /** @var array<int, array<string, array<int, array{grant_type: string, grant_value: string|null}>>> */
    private static array $schemeCache = [];

    /** @var array<string, string|null> */
    private static array $membershipCache = [];

    /** @var array<int, int|null> */
    private static array $projectSchemeCache = [];

    public static function flushCache(): void
    {
        static::$schemeCache = [];
        static::$membershipCache = [];
        static::$projectSchemeCache = [];
    }

    public static function isGoverned(string $permission): bool
    {
        return in_array($permission, self::GOVERNED, true);
    }

    /**
     * @param  Model|null  $subject  the task (or other record) being acted on,
     *                               used by the assignee / reporter grant types
     */
    public function allows(User $user, ?Project $project, string $permission, ?Model $subject = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Gate 1 — the workspace role still decides the ceiling.
        if (! $user->hasPermission($permission)) {
            return false;
        }

        if (! $project || ! self::isGoverned($permission)) {
            return true;
        }

        $grants = $this->grantsFor($project, $permission);

        // Ungoverned by this scheme: gate 1 already said yes.
        if ($grants === []) {
            return true;
        }

        foreach ($grants as $grant) {
            if ($this->matches($user, $project, $grant, $subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{grant_type: string, grant_value: string|null}>
     */
    private function grantsFor(Project $project, string $permission): array
    {
        $schemeId = $this->schemeIdFor($project);

        if ($schemeId === null) {
            return [];
        }

        return $this->scheme($schemeId)[$permission] ?? [];
    }

    private function schemeIdFor(Project $project): ?int
    {
        if ($project->permission_scheme_id) {
            return (int) $project->permission_scheme_id;
        }

        return static::$projectSchemeCache[0] ??= PermissionScheme::query()
            ->where('is_default', true)
            ->value('id');
    }

    /**
     * @return array<string, array<int, array{grant_type: string, grant_value: string|null}>>
     */
    private function scheme(int $id): array
    {
        return static::$schemeCache[$id] ??= DB::table('permission_scheme_grants')
            ->where('permission_scheme_id', $id)
            ->get(['permission', 'grant_type', 'grant_value'])
            ->groupBy('permission')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'grant_type' => $r->grant_type,
                'grant_value' => $r->grant_value,
            ])->all())
            ->all();
    }

    /**
     * @param  array{grant_type: string, grant_value: string|null}  $grant
     */
    private function matches(User $user, Project $project, array $grant, ?Model $subject): bool
    {
        $value = $grant['grant_value'];

        return match ($grant['grant_type']) {
            Grant::TYPE_EVERYONE => true,
            Grant::TYPE_PROJECT_OWNER => (int) $project->owner_id === $user->id,
            Grant::TYPE_ANY_MEMBER => $this->projectRole($user, $project) !== null
                || (int) $project->owner_id === $user->id,
            Grant::TYPE_PROJECT_ROLE => $this->projectRole($user, $project) === $value
                || ($value === 'owner' && (int) $project->owner_id === $user->id),
            Grant::TYPE_ASSIGNEE => $subject !== null
                && (int) ($subject->getAttribute('assignee_id') ?? 0) === $user->id,
            Grant::TYPE_REPORTER => $subject !== null
                && (int) ($subject->getAttribute('created_by_id') ?? 0) === $user->id,
            Grant::TYPE_TEAM => $value !== null && in_array((int) $value, $user->teamIds(), true),
            Grant::TYPE_USER => (int) $value === $user->id,
            Grant::TYPE_WORKSPACE_ROLE => $value !== null && $user->hasRole($value),
            default => false,
        };
    }

    private function projectRole(User $user, Project $project): ?string
    {
        $key = $project->id.':'.$user->id;

        if (array_key_exists($key, static::$membershipCache)) {
            return static::$membershipCache[$key];
        }

        return static::$membershipCache[$key] = DB::table('project_user')
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->value('role');
    }
}
