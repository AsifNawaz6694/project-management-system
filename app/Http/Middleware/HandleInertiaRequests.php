<?php

namespace App\Http\Middleware;

use App\Modules\NotificationCenter\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing(['roles.permissions', 'directPermissions', 'department']);

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'phone' => $user->phone,
                    'job_title' => $user->job_title,
                    'department' => $user->departmentName(),
                    'department_id' => $user->department_id,
                    'status' => $user->status,
                    'initials' => $user->initials,
                    'roles' => $user->roles->map(fn ($role) => [
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ])->values(),
                    'primary_role' => $user->primaryRole()?->slug,
                    'permissions' => $user->permissionSlugs(),
                    'is_admin' => $user->isAdmin(),
                    'is_manager' => $user->isManager(),
                    'is_employee' => $user->isEmployee(),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $user
                ? [
                    'unread' => Notification::query()
                        ->where('user_id', $user->id)
                        ->whereNull('read_at')
                        ->count(),
                ]
                : null,
        ]);
    }
}
