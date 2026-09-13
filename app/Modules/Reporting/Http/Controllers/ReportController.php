<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Reporting\Services\TaskMetricsService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly TaskMetricsService $metrics) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $range = (int) $request->integer('range', 30);
        $range = in_array($range, [7, 30, 90, 180], true) ? $range : 30;
        $from = now()->subDays($range)->startOfDay();

        // Workspace-wide reporting is `reports.view-all` — held by the Super
        // Admin and the Manager. Everyone else gets the same report computed
        // over the rows they can already see: their own work, in the projects
        // they are working in. The scope is a user, or null for "no narrowing",
        // and it is threaded through every query below so that no tile, chart
        // or list can quietly answer a wider question than the rest of the page.
        $scope = ($user->isAdmin() || $user->hasPermission('reports.view-all')) ? null : $user;

        Activity::log('reports.viewed', [
            'module' => 'reports',
            'description' => "Viewed reports dashboard (last {$range}d)",
            'properties' => ['range_days' => $range, 'scope' => $scope ? 'own' : 'workspace'],
        ]);

        return Inertia::render('reports/index', [
            'range' => $range,
            'scoped' => $scope !== null,
            'overview' => $this->overview($from, $scope),
            'projectsByStatus' => $this->projectsByStatus($scope),
            'tasksByStatus' => $this->tasksByStatus($scope),
            'tasksByPriority' => $this->tasksByPriority($scope),
            'taskTrend' => $this->taskTrend($range, $scope),
            // People metrics rank colleagues against one another. They are only
            // meaningful workspace-wide, and handing someone a slice of them
            // while the rest of the page is scoped is how a report leaks.
            'topPerformers' => $scope ? [] : $this->metrics->topCompleters($from),
            'workloadByUser' => $scope ? [] : $this->metrics->workloadByUser(),
            'workloadByTeam' => $scope ? [] : $this->metrics->workloadByTeam(),
            'projectHealth' => $this->projectHealth($scope),
            'recentActivity' => $this->recentActivity($scope),
            // Flow metrics — none of these existed before.
            'flow' => [
                'lead_time_hours' => $this->metrics->leadTimeHours($from, $scope),
                'cycle_time_hours' => $this->metrics->cycleTimeHours($from, $scope),
                'overdue_rate' => $this->metrics->overdueRate($scope),
            ],
            'aging' => $this->metrics->agingBuckets($scope),
        ]);
    }

    /**
     * Projects this report is allowed to count.
     */
    private function projects(?User $scope): Builder
    {
        $query = Project::query();

        return $scope ? $query->visibleTo($scope) : $query;
    }

    /**
     * Root, non-archived tasks this report is allowed to count — the same base
     * TaskMetricsService uses, so the tiles and the flow metrics agree.
     */
    private function tasks(?User $scope): Builder
    {
        $query = Task::query()->root()->notArchived();

        return $scope ? $query->visibleTo($scope) : $query;
    }

    private function overview(Carbon $from, ?User $scope): array
    {
        return [
            'projects_total' => $this->projects($scope)->count(),
            'projects_active' => $this->projects($scope)->where('status', 'active')->count(),
            'projects_completed' => $this->projects($scope)->where('status', 'completed')->count(),
            'tasks_total' => $this->tasks($scope)->count(),
            // Completion read from completed_at, not from a hardcoded status key,
            // so custom "done" statuses count correctly.
            'tasks_completed' => $this->tasks($scope)->whereNotNull('completed_at')->count(),
            'tasks_open' => $this->tasks($scope)->whereNull('completed_at')->count(),
            'tasks_overdue' => $this->tasks($scope)->whereNull('completed_at')
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
            // Only meaningful workspace-wide. A scoped report puts its own open
            // work in that tile instead, so nothing is left showing a zero.
            'users_active' => $scope ? 0 : User::where('status', 'active')->count(),
            'created_projects_in_range' => $this->projects($scope)->where('created_at', '>=', $from)->count(),
            'completed_tasks_in_range' => $this->tasks($scope)->whereNotNull('completed_at')->where('completed_at', '>=', $from)->count(),
        ];
    }

    private function projectsByStatus(?User $scope): array
    {
        $counts = $this->projects($scope)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        return collect(Project::STATUSES)->map(fn ($s) => [
            'key' => $s,
            'value' => (int) ($counts[$s] ?? 0),
        ])->all();
    }

    private function tasksByStatus(?User $scope): array
    {
        $counts = $this->tasks($scope)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        // Iterate configured statuses so custom ones appear in reporting.
        $keys = WorkflowStatus::query()->orderBy('position')->pluck('key')->unique();

        if ($keys->isEmpty()) {
            $keys = collect(Task::STATUSES);
        }

        return $keys->map(fn ($s) => [
            'key' => $s,
            'value' => (int) ($counts[$s] ?? 0),
        ])->values()->all();
    }

    private function tasksByPriority(?User $scope): array
    {
        $counts = $this->tasks($scope)
            ->groupBy('priority')
            ->selectRaw('priority, COUNT(*) as aggregate')
            ->pluck('aggregate', 'priority');

        return collect(Task::PRIORITIES)->map(fn ($p) => [
            'key' => $p,
            'value' => (int) ($counts[$p] ?? 0),
        ])->all();
    }

    private function taskTrend(int $days, ?User $scope): array
    {
        $bucket = $days <= 30 ? 'day' : 'week';
        $points = $bucket === 'day' ? $days : (int) ceil($days / 7);

        return collect(range($points - 1, 0))->map(function ($i) use ($bucket, $scope) {
            $end = $bucket === 'day' ? now()->subDays($i)->endOfDay() : now()->subWeeks($i)->endOfWeek();
            $start = $bucket === 'day' ? now()->subDays($i)->startOfDay() : now()->subWeeks($i)->startOfWeek();

            return [
                'label' => $bucket === 'day' ? $start->format('M j') : 'W'.$start->isoWeek(),
                // root() applied consistently with overview(), so the tiles and
                // this chart can no longer disagree about what a task is.
                'created' => $this->tasks($scope)->whereBetween('created_at', [$start, $end])->count(),
                'completed' => $this->tasks($scope)->whereBetween('completed_at', [$start, $end])->count(),
            ];
        })->values()->all();
    }

    private function projectHealth(?User $scope): array
    {
        return $this->projects($scope)
            ->with(['owner:id,name'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'slug', 'title', 'color', 'status', 'progress', 'end_date', 'owner_id'])
            ->map(function (Project $p) {
                $overdue = $p->end_date && $p->end_date->isPast() && $p->status !== 'completed';

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'color' => $p->color,
                    'status' => $p->status,
                    'progress' => $p->progress,
                    'overdue' => $overdue,
                    'owner' => $p->owner?->name,
                ];
            })->all();
    }

    private function recentActivity(?User $scope): array
    {
        return Activity::query()
            ->with('user:id,name,avatar')
            // A scoped report shows the viewer their own trail, not the workspace's.
            ->when($scope, fn (Builder $q, User $u) => $q->where(fn (Builder $w) => $w
                ->where('user_id', $u->id)
                ->orWhere('subject_user_id', $u->id)))
            ->chronological()
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
