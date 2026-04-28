<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Http\Requests\StoreRoleRequest;
use App\Modules\UserManagement\Http\Requests\UpdateRolePermissionsRequest;
use App\Modules\UserManagement\Http\Requests\UpdateRoleRequest;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions:id,slug')
            ->withCount('users')
            ->orderBy('id')
            ->get();

        $permissions = Permission::orderBy('module')->orderBy('id')->get(['id', 'slug', 'name', 'module']);

        return Inertia::render('roles/index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permission_slugs' => $role->permissions->pluck('slug')->all(),
            ]),
            'modules' => collect(PermissionRegistry::modules())->map(fn ($cfg, $key) => [
                'key' => $key,
                'label' => $cfg['label'],
                'permissions' => collect($cfg['permissions'])->map(fn ($name, $slug) => [
                    'slug' => $slug,
                    'name' => $name,
                ])->values(),
            ])->values(),
            'permissions' => $permissions,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $slug = $request->input('slug') ?: Str::slug($request->input('name'));
        $base = $slug;
        $i = 2;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $role = Role::create([
            'slug' => $slug,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'is_system' => false,
        ]);

        $perms = $request->input('permissions', []);
        $role->syncPermissionsBySlug($perms);

        Activity::log('role.created', [
            'module' => 'roles',
            'description' => "Created role \"{$role->name}\" with ".count($perms).' permission(s)',
            'properties' => ['role_id' => $role->id, 'role' => $role->slug, 'permissions' => $perms],
        ]);

        return redirect()->route('roles.index')->with('status', "Role {$role->name} created.");
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->slug === Role::ADMIN) {
            Activity::log('role.permissions-blocked', [
                'module' => 'roles',
                'description' => 'Attempted to modify Administrator permissions (blocked)',
                'properties' => ['role' => $role->slug],
            ]);

            return back()->with('status', 'Admin role permissions cannot be modified.');
        }

        $previous = $role->permissions()->pluck('slug')->all();
        $next = $request->input('permissions', []);
        $role->syncPermissionsBySlug($next);

        $added = array_values(array_diff($next, $previous));
        $removed = array_values(array_diff($previous, $next));

        Activity::log('role.permissions-updated', [
            'module' => 'roles',
            'description' => "Updated permissions for role {$role->name} (".count($added).' added, '.count($removed).' removed)',
            'properties' => ['role_id' => $role->id, 'role' => $role->slug, 'added' => $added, 'removed' => $removed],
        ]);

        return back()->with('status', "Permissions updated for {$role->name}.");
    }

    public function rename(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('status', 'System roles cannot be renamed.');
        }

        $original = ['name' => $role->name, 'description' => $role->description];
        $role->fill($request->only(['name', 'description', 'slug']))->save();

        Activity::log('role.updated', [
            'module' => 'roles',
            'description' => "Renamed role from \"{$original['name']}\" to \"{$role->name}\"",
            'properties' => ['role_id' => $role->id, 'role' => $role->slug, 'original' => $original],
        ]);

        return back()->with('status', "Role updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if (! $request->user()->hasPermission('roles.delete')) {
            abort(403);
        }

        if ($role->is_system) {
            return back()->with('status', 'System roles cannot be deleted.');
        }

        $usersCount = $role->users()->count();
        $name = $role->name;
        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        Activity::log('role.deleted', [
            'module' => 'roles',
            'description' => "Deleted role \"{$name}\" (was assigned to {$usersCount} user(s))",
            'properties' => ['role' => $role->slug, 'users_affected' => $usersCount],
        ]);

        return redirect()->route('roles.index')->with('status', "Role {$name} deleted.");
    }
}
