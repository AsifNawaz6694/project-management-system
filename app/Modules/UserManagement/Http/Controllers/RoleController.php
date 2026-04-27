<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\UserManagement\Http\Requests\UpdateRolePermissionsRequest;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
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

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->slug === Role::ADMIN) {
            return back()->with('status', 'Admin role permissions cannot be modified.');
        }

        $role->syncPermissionsBySlug($request->input('permissions', []));

        Activity::log('role.permissions-updated', [
            'module' => 'roles',
            'description' => "Updated permissions for role {$role->name}",
            'properties' => ['role' => $role->slug, 'permissions' => $request->input('permissions', [])],
        ]);

        return back()->with('status', "Permissions updated for {$role->name}.");
    }
}
