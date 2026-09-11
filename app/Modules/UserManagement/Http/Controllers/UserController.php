<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\UserManagement\Http\Requests\StoreUserRequest;
use App\Modules\UserManagement\Http\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Department;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role', 'status', 'department']);

        $users = User::query()
            ->with(['roles:id,slug,name', 'department:id,slug,name'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($d) => $d->where('name', 'like', "%{$search}%"));
                });
            })
            // Arrays from the multi-select filters; scalars still accepted.
            ->when($filters['role'] ?? null, function ($q, $role) {
                $q->whereHas('roles', fn ($q) => $q->whereIn('slug', (array) $role));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->whereIn('status', (array) $status))
            ->when($filters['department'] ?? null, function ($q, $department) {
                $q->whereHas('department', fn ($d) => $d->whereIn('slug', (array) $department));
            })
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'admins' => User::whereHas('roles', fn ($q) => $q->where('slug', Role::ADMIN))->count(),
            'managers' => User::whereHas('roles', fn ($q) => $q->where('slug', Role::MANAGER))->count(),
        ];

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('id')->get(['id', 'slug', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'slug', 'name']),
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'roles' => Role::orderBy('id')->get(['id', 'slug', 'name', 'description']),
            'departments' => Department::orderBy('name')->get(['id', 'slug', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()
            ->route('users.show', $user)
            ->with('status', "User {$user->name} created.");
    }

    public function show(User $user): Response
    {
        $user->load([
            'roles.permissions:id,slug,name,module',
            'directPermissions:id,slug,name,module',
            'department:id,slug,name',
            'teams:id,slug,name,color',
        ]);

        $activities = Activity::query()
            ->where('subject_user_id', $user->id)
            ->orWhere('user_id', $user->id)
            ->latest()
            ->limit(40)
            ->get();

        return Inertia::render('users/show', [
            'user' => $user,
            'activities' => $activities,
            'permissionSlugs' => $user->permissionSlugs(),
            'directPermissionSlugs' => $user->directPermissions->pluck('slug'),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load(['roles:id,slug', 'department:id,slug,name']);

        return Inertia::render('users/edit', [
            'user' => $user,
            'roles' => Role::orderBy('id')->get(['id', 'slug', 'name', 'description']),
            'departments' => Department::orderBy('name')->get(['id', 'slug', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated());

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()->hasPermission('users.delete')) {
            abort(403);
        }

        if ($user->id === $request->user()->id) {
            return back()->with('status', 'You cannot delete your own account.');
        }

        $this->users->delete($user);

        return redirect()
            ->route('users.index')
            ->with('status', 'User deleted.');
    }
}
