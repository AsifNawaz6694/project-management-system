<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Reporting\Services\TaskMetricsService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly TaskMetricsService $metrics) {}

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
            'taskTrend' => $this->taskTrend($range),
            'topPerformers' => $this->metrics->topCompleters($from),
            'projectHealth' => $this->projectHealth(),
            'recentActivity' => $this->recentActivity(),
            // Flow metrics — none of these existed before.
            'flow' => [
                'lead_time_hours' => $this->metrics->leadTimeHours($from),
                'cycle_time_hours' => $this->metrics->cycleTimeHours($from),
                'overdue_rate' => $this->metrics->overdueRate(),
            ],
            'aging' => $this->metrics->agingBuckets(),
            'workloadByUser' => $this->metrics->workloadByUser(),
            'workloadByTeam' => $this->metrics->workloadByTeam(),
        ]);
    }

    private function overview(Carbon $from): array
    {
        return [
            'projects_total' => Project::count(),
            'projects_active' => Project::where('status', 'active')->count(),
            'projects_completed' => Project::where('status', 'completed')->count(),
            'tasks_total' => Task::root()->notArchived()->count(),
            // Completion read from completed_at, not from a hardcoded status key,
            // so custom "done" statuses count correctly.
            'tasks_completed' => Task::root()->notArchived()->whereNotNull('completed_at')->count(),
            'tasks_overdue' => Task::root()->notArchived()->whereNull('completed_at')
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
            'users_active' => User::where('status', 'active')->count(),
            'created_projects_in_range' => Project::where('created_at', '>=', $from)->count(),
            'completed_tasks_in_range' => Task::root()->notArchived()->whereNotNull('completed_at')->where('completed_at', '>=', $from)->count(),
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
        $counts = Task::root()->notArchived()
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

    private function tasksByPriority(): array
    {
        $counts = Task::root()->notArchived()
            ->groupBy('priority')
            ->selectRaw('priority, COUNT(*) as aggregate')
            ->pluck('aggregate', 'priority');

        return collect(Task::PRIORITIES)->map(fn ($p) => [
            'key' => $p,
            'value' => (int) ($counts[$p] ?? 0),
        ])->all();
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
                // root() applied consistently with overview(), so the tiles and
                // this chart can no longer disagree about what a task is.
                'created' => Task::root()->notArchived()->whereBetween('created_at', [$start, $end])->count(),
                'completed' => Task::root()->notArchived()->whereBetween('completed_at', [$start, $end])->count(),
            ];
        })->values()->all();
    }

    private function projectHealth(): array
    {
        return Project::query()
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
