<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\ExpenseManagement\Models\Expense;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isLeadership = $user->isAdmin() || $user->isManager();

        $payload = $isLeadership
            ? $this->leadershipPayload($user)
            : $this->employeePayload($user);

        return Inertia::render('dashboard', array_merge([
            'role' => $user->primaryRole()?->slug,
        ], $payload));
    }

    private function leadershipPayload(User $user): array
    {
        $projects = Project::query();
        $tasks = Task::query()->root();
        $expenses = Expense::query();

        $projectsTotal = (clone $projects)->count();
        $projectsActive = (clone $projects)->where('status', 'active')->count();
        $projectsCompleted = (clone $projects)->where('status', 'completed')->count();
        $projectsAtRisk = (clone $projects)
            ->whereIn('status', ['active', 'planning'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays(7))
            ->count();

        $tasksTotal = (clone $tasks)->count();
        $tasksDone = (clone $tasks)->where('status', 'completed')->count();
        $tasksProgress = (clone $tasks)->where('status', 'in_progress')->count();
        $tasksOverdue = (clone $tasks)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();

        $totalBudget = (float) Project::sum('budget');
        $totalSpent = (float) Expense::where('status', 'approved')->sum('amount');
        $pendingApproval = (clone $expenses)->where('status', 'pending')->count();
        $pendingApprovalAmount = (float) (clone $expenses)->where('status', 'pending')->sum('amount');

        $statusBreakdown = [
            ['key' => 'planning', 'label' => 'Planning', 'value' => (clone $projects)->where('status', 'planning')->count(), 'tone' => 'from-blue-500 to-cyan-500'],
            ['key' => 'active', 'label' => 'Active', 'value' => $projectsActive, 'tone' => 'from-emerald-500 to-teal-600'],
            ['key' => 'on_hold', 'label' => 'On hold', 'value' => (clone $projects)->where('status', 'on_hold')->count(), 'tone' => 'from-amber-400 to-orange-500'],
            ['key' => 'completed', 'label' => 'Completed', 'value' => $projectsCompleted, 'tone' => 'from-violet-500 to-purple-600'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'value' => (clone $projects)->where('status', 'cancelled')->count(), 'tone' => 'from-slate-500 to-slate-700'],
        ];

        $burndown = collect(range(13, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $created = Task::whereDate('created_at', $date)->count();
            $completed = Task::whereDate('completed_at', $date)->count();

            return [
                'label' => $date->format('M j'),
                'created' => $created,
                'completed' => $completed,
            ];
        })->values();

        $topProjects = Project::query()
            ->orderByDesc('progress')
            ->limit(5)
            ->get(['id', 'slug', 'title', 'color', 'progress', 'status', 'budget'])
            ->map(function (Project $p) {
                $approved = (float) Expense::where('project_id', $p->id)->where('status', 'approved')->sum('amount');
                $budget = (float) ($p->budget ?? 0);

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'color' => $p->color,
                    'progress' => $p->progress,
                    'status' => $p->status,
                    'budget' => $budget,
                    'spent' => $approved,
                    'utilization' => $budget > 0 ? min(100, (int) round($approved / $budget * 100)) : 0,
                ];
            });

        $topPerformers = User::query()
            ->withCount(['performedActivities as completed_tasks' => fn ($q) => $q->where('action', 'task.status-changed')->whereJsonContains('properties->to', 'completed')])
            ->orderByDesc('completed_tasks')
            ->limit(5)
            ->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'initials' => $u->initials,
                'job_title' => $u->job_title,
                'completed_tasks' => $u->completed_tasks ?? 0,
            ]);

        $upcomingDeadlines = Task::query()
            ->root()
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->subDay())
            ->orderBy('due_date')
            ->limit(8)
            ->with(['project:id,slug,title,color', 'assignee:id,name,avatar'])
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => $t->due_date?->toDateString(),
                'status' => $t->status,
                'priority' => $t->priority,
                'project' => $t->project,
                'assignee' => $t->assignee ? [
                    'id' => $t->assignee->id,
                    'name' => $t->assignee->name,
                    'initials' => $t->assignee->initials,
                ] : null,
            ]);

        $recentActivity = Activity::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'action' => $a->action,
                'module' => $a->module,
                'created_at' => $a->created_at?->toISOString(),
            ]);

        return [
            'kpis' => [
                ['label' => 'Active projects', 'value' => $projectsActive, 'sub' => $projectsTotal.' total · '.$projectsAtRisk.' at risk', 'icon' => 'folder-kanban', 'accent' => 'violet'],
                ['label' => 'Tasks in flight', 'value' => $tasksProgress, 'sub' => $tasksOverdue.' overdue · '.$tasksDone.' done', 'icon' => 'list-checks', 'accent' => 'amber'],
                ['label' => 'Approved spend', 'value' => $this->money($totalSpent), 'sub' => 'Of '.$this->money($totalBudget).' budget', 'icon' => 'wallet', 'accent' => 'emerald'],
                ['label' => 'Pending approvals', 'value' => $pendingApproval, 'sub' => $this->money($pendingApprovalAmount).' awaiting', 'icon' => 'hourglass', 'accent' => 'rose'],
            ],
            'completion' => [
                'total_tasks' => $tasksTotal,
                'completed_tasks' => $tasksDone,
                'completion_rate' => $tasksTotal > 0 ? (int) round($tasksDone / $tasksTotal * 100) : 0,
            ],
            'budget' => [
                'total' => $totalBudget,
                'spent' => $totalSpent,
                'utilization' => $totalBudget > 0 ? min(100, (int) round($totalSpent / $totalBudget * 100)) : 0,
            ],
            'statusBreakdown' => $statusBreakdown,
            'burndown' => $burndown,
            'topProjects' => $topProjects,
            'topPerformers' => $topPerformers,
            'upcomingDeadlines' => $upcomingDeadlines,
            'recentActivity' => $recentActivity,
            'unreadNotifications' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    private function employeePayload(User $user): array
    {
        $myTasks = Task::query()->root()->where('assignee_id', $user->id);

        $todo = (clone $myTasks)->where('status', 'todo')->count();
        $inProgress = (clone $myTasks)->where('status', 'in_progress')->count();
        $completed = (clone $myTasks)->where('status', 'completed')->count();
        $overdue = (clone $myTasks)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();
        $totalForMe = (clone $myTasks)->count();

        $myExpenses = Expense::query()->where('user_id', $user->id);
        $expensesPending = (clone $myExpenses)->where('status', 'pending')->count();
        $expensesApproved = (float) (clone $myExpenses)->where('status', 'approved')->sum('amount');
        $expensesPendingAmount = (float) (clone $myExpenses)->where('status', 'pending')->sum('amount');

        $velocity = collect(range(6, 0))->map(function ($daysAgo) use ($user) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $completed = Task::where('assignee_id', $user->id)->whereDate('completed_at', $date)->count();

            return [
                'label' => $date->format('D'),
                'completed' => $completed,
                'created' => 0,
            ];
        })->values();

        $upcoming = (clone $myTasks)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(8)
            ->with('project:id,slug,title,color')
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_date' => $t->due_date?->toDateString(),
                'status' => $t->status,
                'priority' => $t->priority,
                'project' => $t->project,
            ]);

        $myProjects = Project::visibleTo($user)
            ->limit(5)
            ->get(['id', 'slug', 'title', 'color', 'progress', 'status'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'color' => $p->color,
                'progress' => $p->progress,
                'status' => $p->status,
            ]);

        $recentActivity = Activity::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('subject_user_id', $user->id);
            })
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'action' => $a->action,
                'module' => $a->module,
                'created_at' => $a->created_at?->toISOString(),
            ]);

        return [
            'kpis' => [
                ['label' => 'My open tasks', 'value' => $todo + $inProgress, 'sub' => $todo.' to do · '.$inProgress.' in progress', 'icon' => 'list-checks', 'accent' => 'violet'],
                ['label' => 'Completed (all-time)', 'value' => $completed, 'sub' => $totalForMe.' total assigned', 'icon' => 'check-circle-2', 'accent' => 'emerald'],
                ['label' => 'Overdue', 'value' => $overdue, 'sub' => $overdue === 0 ? 'You\'re on track' : 'Needs attention', 'icon' => 'alert-circle', 'accent' => 'rose'],
                ['label' => 'My expenses', 'value' => $expensesPending, 'sub' => $this->money($expensesPendingAmount).' pending · '.$this->money($expensesApproved).' approved', 'icon' => 'wallet', 'accent' => 'amber'],
            ],
            'completion' => [
                'total_tasks' => $totalForMe,
                'completed_tasks' => $completed,
                'completion_rate' => $totalForMe > 0 ? (int) round($completed / $totalForMe * 100) : 0,
            ],
            'velocity' => $velocity,
            'upcomingDeadlines' => $upcoming,
            'myProjects' => $myProjects,
            'recentActivity' => $recentActivity,
            'unreadNotifications' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    private function money(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return '$'.number_format($amount / 1_000_000, 1).'M';
        }
        if ($amount >= 1_000) {
            return '$'.number_format($amount / 1_000, 1).'k';
        }

        return '$'.number_format($amount, 0);
    }
}
