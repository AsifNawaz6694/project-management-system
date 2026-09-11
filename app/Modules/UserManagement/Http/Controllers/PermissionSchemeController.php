<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\PermissionScheme;
use App\Modules\UserManagement\Models\PermissionSchemeGrant as Grant;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use App\Modules\UserManagement\Services\ProjectPermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PermissionSchemeController extends Controller
{
    public function index(Request $request): Response
    {
        $schemes = PermissionScheme::query()
            ->withCount(['grants', 'projects'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $user = $request->user();

        return Inertia::render('permission-schemes/index', [
            'schemes' => $schemes->map(fn (PermissionScheme $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'is_default' => $s->is_default,
                'is_system' => $s->is_system,
                'grants_count' => $s->grants_count,
                'projects_count' => $s->projects_count,
            ]),
            'can' => ['manage' => $user->isAdmin() || $user->hasPermission('permission-schemes.manage')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('permission_schemes', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'copy_from' => ['nullable', 'integer', 'exists:permission_schemes,id'],
        ]);

        $scheme = PermissionScheme::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => false,
            'is_system' => false,
        ]);

        // Starting from a copy is far more useful than starting from nothing:
        // a scheme with no grants governs nothing, which reads as broken.
        $source = $data['copy_from'] ?? PermissionScheme::query()->where('is_default', true)->value('id');

        if ($source) {
            foreach (Grant::query()->where('permission_scheme_id', $source)->get() as $row) {
                $scheme->grants()->create([
                    'permission' => $row->permission,
                    'grant_type' => $row->grant_type,
                    'grant_value' => $row->grant_value,
                ]);
            }
        }

        return redirect()
            ->route('permission-schemes.edit', $scheme)
            ->with('status', 'Scheme created.');
    }

    public function edit(PermissionScheme $permissionScheme): Response
    {
        $permissionScheme->load('grants');

        $labels = [];
        foreach (PermissionRegistry::modules() as $module) {
            foreach ($module['permissions'] as $slug => $label) {
                $labels[$slug] = $label;
            }
        }

        return Inertia::render('permission-schemes/edit', [
            'scheme' => [
                'id' => $permissionScheme->id,
                'name' => $permissionScheme->name,
                'description' => $permissionScheme->description,
                'is_default' => $permissionScheme->is_default,
                'is_system' => $permissionScheme->is_system,
            ],
            'permissions' => collect(ProjectPermissionResolver::GOVERNED)
                ->map(fn (string $slug) => [
                    'slug' => $slug,
                    'label' => $labels[$slug] ?? $slug,
                    'module' => explode('.', $slug)[0],
                ])
                ->values(),
            'grants' => $permissionScheme->grants
                ->map(fn (Grant $g) => [
                    'permission' => $g->permission,
                    'grant_type' => $g->grant_type,
                    'grant_value' => $g->grant_value,
                ])
                ->values(),
            'grantTypes' => Grant::TYPES,
            'typesWithValue' => Grant::TYPES_WITH_VALUE,
            'projectRoles' => ProjectPermissionResolver::PROJECT_ROLES,
            'workspaceRoles' => Role::query()->orderBy('name')->get(['slug', 'name'])
                ->map(fn (Role $r) => ['value' => $r->slug, 'label' => $r->name]),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Team $t) => ['value' => (string) $t->id, 'label' => $t->name]),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name]),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title', 'permission_scheme_id'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'assigned' => (int) $p->permission_scheme_id === $permissionScheme->id,
                ]),
        ]);
    }

    public function update(Request $request, PermissionScheme $permissionScheme): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('permission_schemes', 'name')->ignore($permissionScheme->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $permissionScheme->update($data);

        return back()->with('status', 'Scheme updated.');
    }

    /**
     * Replaces the whole grant set in one transaction, the same contract the
     * workflow transition editor uses, so a save is never half-applied.
     */
    public function updateGrants(Request $request, PermissionScheme $permissionScheme): RedirectResponse
    {
        $data = $request->validate([
            'grants' => ['present', 'array'],
            'grants.*.permission' => ['required', 'string', 'max:64'],
            'grants.*.grant_type' => ['required', Rule::in(Grant::TYPES)],
            'grants.*.grant_value' => ['nullable', 'string', 'max:64'],
        ]);

        $seen = [];
        $rows = [];
        $now = now();

        foreach ($data['grants'] as $grant) {
            if (! ProjectPermissionResolver::isGoverned($grant['permission'])) {
                continue; // Silently drop anything a scheme cannot govern.
            }

            $needsValue = in_array($grant['grant_type'], Grant::TYPES_WITH_VALUE, true);
            $value = $needsValue ? ($grant['grant_value'] ?? null) : null;

            if ($needsValue && ($value === null || $value === '')) {
                continue; // A "who" with no argument can never match anyone.
            }

            $key = $grant['permission'].'|'.$grant['grant_type'].'|'.$value;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $rows[] = [
                'permission_scheme_id' => $permissionScheme->id,
                'permission' => $grant['permission'],
                'grant_type' => $grant['grant_type'],
                'grant_value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($permissionScheme, $rows) {
            $permissionScheme->grants()->delete();

            if ($rows !== []) {
                Grant::query()->insert($rows);
            }
        });

        ProjectPermissionResolver::flushCache();

        return back()->with('status', 'Permissions saved.');
    }

    public function assignProjects(Request $request, PermissionScheme $permissionScheme): RedirectResponse
    {
        $data = $request->validate([
            'project_ids' => ['present', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $ids = array_map('intval', $data['project_ids']);

        // Detach anything that moved away, then attach everything selected.
        Project::query()
            ->where('permission_scheme_id', $permissionScheme->id)
            ->when($ids !== [], fn ($q) => $q->whereNotIn('id', $ids))
            ->update(['permission_scheme_id' => null]);

        if ($ids !== []) {
            Project::query()->whereIn('id', $ids)->update(['permission_scheme_id' => $permissionScheme->id]);
        }

        ProjectPermissionResolver::flushCache();

        return back()->with('status', 'Projects updated.');
    }

    public function makeDefault(PermissionScheme $permissionScheme): RedirectResponse
    {
        DB::transaction(function () use ($permissionScheme) {
            PermissionScheme::query()->where('is_default', true)->update(['is_default' => false]);
            $permissionScheme->forceFill(['is_default' => true])->save();
        });

        ProjectPermissionResolver::flushCache();

        return back()->with('status', 'Default scheme updated.');
    }

    public function destroy(PermissionScheme $permissionScheme): RedirectResponse
    {
        if ($permissionScheme->is_default) {
            throw ValidationException::withMessages([
                'scheme' => 'The default scheme cannot be deleted. Make another scheme the default first.',
            ]);
        }

        $inUse = Project::query()->where('permission_scheme_id', $permissionScheme->id)->count();

        if ($inUse > 0) {
            throw ValidationException::withMessages([
                'scheme' => "This scheme is still used by {$inUse} project(s). Move them to another scheme first.",
            ]);
        }

        $permissionScheme->delete();
        ProjectPermissionResolver::flushCache();

        return redirect()
            ->route('permission-schemes.index')
            ->with('status', 'Scheme deleted.');
    }
}
