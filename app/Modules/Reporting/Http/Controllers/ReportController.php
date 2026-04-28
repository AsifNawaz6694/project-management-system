<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ExpenseManagement\Models\Expense;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $range = (int) $request->integer('range', 30);
        $range = in_array($range, [7, 30, 90, 180], true) ? $range : 30;
        $from = now()->subDays($range)->startOfDay();

        Activity::log('reports.viewed', [
            'module' => 'reports',
            'description' => "Viewed reports dashboard (last {$range}d)",
            'properties' => ['range_days' => $range],
        ]);

        return Inertia::render('reports/index', [
            'range' => $range,
            'overview' => $this->overview($from),
            'projectsByStatus' => $this->projectsByStatus(),
            'tasksByStatus' => $this->tasksByStatus(),
            'tasksByPriority' => $this->tasksByPriority(),
            'expensesByCategory' => $this->expensesByCategory($from),
            'expensesTrend' => $this->expensesTrend($range),
            'taskTrend' => $this->taskTrend($range),
            'topPerformers' => $this->topPerformers($from),
            'projectHealth' => $this->projectHealth(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    private function overview(Carbon $from): array
    {
        return [
            'projects_total' => Project::count(),
            'projects_active' => Project::where('status', 'active')->count(),
            'projects_completed' => Project::where('status', 'completed')->count(),
            'tasks_total' => Task::root()->count(),
            'tasks_completed' => Task::root()->where('status', 'completed')->count(),
            'tasks_overdue' => Task::root()->where('status', '!=', 'completed')
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
            'expenses_pending' => (float) Expense::where('status', 'pending')->sum('amount'),
            'expenses_approved' => (float) Expense::where('status', 'approved')->sum('amount'),
            'budget_total' => (float) Project::sum('budget'),
            'users_active' => User::where('status', 'active')->count(),
            'created_projects_in_range' => Project::where('created_at', '>=', $from)->count(),
            'completed_tasks_in_range' => Task::where('status', 'completed')->where('completed_at', '>=', $from)->count(),
        ];
    }

    private function projectsByStatus(): array
    {
        return collect(Project::STATUSES)->map(fn ($s) => [
            'key' => $s,
            'value' => Project::where('status', $s)->count(),
        ])->all();
    }

    private function tasksByStatus(): array
    {
        return collect(Task::STATUSES)->map(fn ($s) => [
            'key' => $s,
            'value' => Task::root()->where('status', $s)->count(),
        ])->all();
    }

    private function tasksByPriority(): array
    {
        return collect(Task::PRIORITIES)->map(fn ($p) => [
            'key' => $p,
            'value' => Task::root()->where('priority', $p)->count(),
        ])->all();
    }

    private function expensesByCategory(Carbon $from): array
    {
        return collect(Expense::CATEGORIES)->map(fn ($c) => [
            'key' => $c,
            'count' => Expense::where('category', $c)->where('expense_date', '>=', $from->toDateString())->count(),
            'amount' => (float) Expense::where('category', $c)->where('expense_date', '>=', $from->toDateString())->sum('amount'),
        ])->all();
    }

    private function expensesTrend(int $days): array
    {
        $bucket = $days <= 30 ? 'day' : 'week';
        $points = $bucket === 'day' ? $days : (int) ceil($days / 7);

        return collect(range($points - 1, 0))->map(function ($i) use ($bucket) {
            $end = $bucket === 'day' ? now()->subDays($i)->endOfDay() : now()->subWeeks($i)->endOfWeek();
            $start = $bucket === 'day' ? now()->subDays($i)->startOfDay() : now()->subWeeks($i)->startOfWeek();
            $approved = (float) Expense::where('status', 'approved')->whereBetween('decided_at', [$start, $end])->sum('amount');
            $submitted = (float) Expense::whereBetween('created_at', [$start, $end])->sum('amount');

            return [
                'label' => $bucket === 'day' ? $start->format('M j') : 'W'.$start->isoWeek(),
                'submitted' => $submitted,
                'approved' => $approved,
            ];
        })->values()->all();
    }

    private function taskTrend(int $days): array
    {
        $bucket = $days <= 30 ? 'day' : 'week';
        $points = $bucket === 'day' ? $days : (int) ceil($days / 7);

        return collect(range($points - 1, 0))->map(function ($i) use ($bucket) {
            $end = $bucket === 'day' ? now()->subDays($i)->endOfDay() : now()->subWeeks($i)->endOfWeek();
            $start = $bucket === 'day' ? now()->subDays($i)->startOfDay() : now()->subWeeks($i)->startOfWeek();

            return [
                'label' => $bucket === 'day' ? $start->format('M j') : 'W'.$start->isoWeek(),
                'created' => Task::whereBetween('created_at', [$start, $end])->count(),
                'completed' => Task::whereBetween('completed_at', [$start, $end])->count(),
            ];
        })->values()->all();
    }

    private function topPerformers(Carbon $from): array
    {
        return User::query()
            ->withCount(['performedActivities as completed_tasks' => fn ($q) => $q
                ->where('action', 'task.status-changed')
                ->whereJsonContains('properties->to', 'completed')
                ->where('created_at', '>=', $from)])
            ->orderByDesc('completed_tasks')
            ->limit(8)
            ->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
                'job_title' => $u->job_title,
                'completed_tasks' => (int) ($u->completed_tasks ?? 0),
            ])->all();
    }

    private function projectHealth(): array
    {
        return Project::query()
            ->with(['owner:id,name'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'slug', 'title', 'color', 'status', 'progress', 'budget', 'currency', 'end_date', 'owner_id'])
            ->map(function (Project $p) {
                $approved = (float) Expense::where('project_id', $p->id)->where('status', 'approved')->sum('amount');
                $budget = (float) ($p->budget ?? 0);
                $overdue = $p->end_date && $p->end_date->isPast() && $p->status !== 'completed';

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'color' => $p->color,
                    'status' => $p->status,
                    'progress' => $p->progress,
                    'budget' => $budget,
                    'spent' => $approved,
                    'currency' => $p->currency ?? 'SAR',
                    'utilization' => $budget > 0 ? min(999, (int) round($approved / $budget * 100)) : 0,
                    'over_budget' => $budget > 0 && $approved > $budget,
                    'overdue' => $overdue,
                    'owner' => $p->owner?->name,
                ];
            })->all();
    }

    private function recentActivity(): array
    {
        return Activity::query()
            ->with('user:id,name,avatar')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'module' => $a->module,
                'created_at' => $a->created_at?->toISOString(),
                'actor' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            ])->all();
    }
}
