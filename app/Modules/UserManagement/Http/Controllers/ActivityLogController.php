<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['module', 'user_id', 'action', 'date_from', 'date_to', 'search']);

        $query = Activity::query()
            ->with(['user:id,name,avatar', 'subjectUser:id,name,avatar'])
            ->when($filters['module'] ?? null, fn ($q, $m) => $q->where('module', $m))
            ->when($filters['user_id'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('description', 'like', "%{$s}%"));

        $activities = $query->latest()->paginate(50)->withQueryString();

        $modules = Activity::query()->select('module')->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $actions = Activity::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return Inertia::render('activity/index', [
            'activities' => $activities,
            'filters' => $filters,
            'modules' => $modules,
            'actions' => $actions,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => Activity::count(),
                'today' => Activity::whereDate('created_at', today())->count(),
                'this_week' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
                'auth_failures' => Activity::where('action', 'auth.login-failed')->count(),
            ],
        ]);
    }
}
