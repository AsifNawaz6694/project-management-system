<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskStatusHistory;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Flow metrics derived from tasks and their status history.
 *
 * Two deliberate corrections over the previous reporting code:
 *  - completion is counted from tasks.completed_at, not from audit-log rows,
 *    so pruning the activity log cannot rewrite history;
 *  - every query applies root() consistently, so the summary tiles and the
 *    trend chart cannot disagree about what "a task" is.
 */
class TaskMetricsService
{
    /**
     * Average wall-clock time from creation to completion, in hours.
     */
    public function leadTimeHours(Carbon $from, ?User $scope = null): ?float
    {
        $rows = $this->base($scope)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->get(['created_at', 'completed_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $total = $rows->sum(fn ($t) => $t->completed_at->diffInSeconds($t->created_at, absolute: true));

        return round($total / $rows->count() / 3600, 1);
    }

    /**
     * Average time from first leaving the initial status to completion.
     * This is "time actually being worked", as distinct from lead time.
     */
    public function cycleTimeHours(Carbon $from, ?User $scope = null): ?float
    {
        $completed = $this->base($scope)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $from)
            ->pluck('completed_at', 'id');

        if ($completed->isEmpty()) {
            return null;
        }

        $starts = TaskStatusHistory::query()
            ->whereIn('task_id', $completed->keys())
            ->whereNotNull('from_status')
            ->selectRaw('task_id, MIN(created_at) as started_at')
            ->groupBy('task_id')
            ->pluck('started_at', 'task_id');

        $durations = [];

        foreach ($completed as $taskId => $completedAt) {
            $startedAt = $starts[$taskId] ?? null;
            if (! $startedAt) {
                continue;
            }

            $durations[] = Carbon::parse($completedAt)->diffInSeconds(Carbon::parse($startedAt), absolute: true);
        }

        if ($durations === []) {
            return null;
        }

        return round(array_sum($durations) / count($durations) / 3600, 1);
    }

    /**
     * Open tasks bucketed by how long they have been open.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function agingBuckets(?User $scope = null): array
    {
        $doneKeys = $this->doneStatusKeys();

        $open = $this->base($scope)
            ->whereNull('completed_at')
            ->whereNotIn('status', $doneKeys ?: ['__none__'])
            ->pluck('created_at');

        $buckets = ['0–2 days' => 0, '3–7 days' => 0, '8–30 days' => 0, '30+ days' => 0];

        foreach ($open as $createdAt) {
            $days = Carbon::parse($createdAt)->diffInDays(now(), absolute: true);

            match (true) {
                $days <= 2 => $buckets['0–2 days']++,
                $days <= 7 => $buckets['3–7 days']++,
                $days <= 30 => $buckets['8–30 days']++,
                default => $buckets['30+ days']++,
            };
        }

        return collect($buckets)->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all();
    }

    /**
     * Current open workload per assignee, with estimated effort.
     *
     * @return array<int, array<string, mixed>>
     */
    public function workloadByUser(int $limit = 10): array
    {
        $doneKeys = $this->doneStatusKeys();

        $rows = DB::table('tasks')
            ->join('users', 'users.id', '=', 'tasks.assignee_id')
            ->whereNull('tasks.deleted_at')
            ->whereNull('tasks.parent_task_id')
            ->whereNull('tasks.archived_at')
            ->whereNull('tasks.completed_at')
            ->whereNotIn('tasks.status', $doneKeys ?: ['__none__'])
            ->groupBy('users.id', 'users.name', 'users.job_title')
            ->orderByDesc(DB::raw('COUNT(tasks.id)'))
            ->limit($limit)
            ->get([
                'users.id',
                'users.name',
                'users.job_title',
                DB::raw('COUNT(tasks.id) as open_tasks'),
                DB::raw('COALESCE(SUM(tasks.estimate_minutes), 0) as estimated_minutes'),
            ]);

        // Overdue counts in a second grouped query, so no driver-specific date
        // function or hand-positioned binding is needed in the aggregate above.
        $overdue = DB::table('tasks')
            ->whereNull('tasks.deleted_at')
            ->whereNull('tasks.parent_task_id')
            ->whereNull('tasks.archived_at')
            ->whereNull('tasks.completed_at')
            ->whereNotNull('tasks.due_date')
            ->whereDate('tasks.due_date', '<', now()->toDateString())
            ->whereIn('tasks.assignee_id', $rows->pluck('id'))
            ->groupBy('tasks.assignee_id')
            ->pluck(DB::raw('COUNT(*)'), 'tasks.assignee_id');

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'job_title' => $r->job_title,
            'open_tasks' => (int) $r->open_tasks,
            'estimated_minutes' => (int) $r->estimated_minutes,
            'overdue_tasks' => (int) ($overdue[$r->id] ?? 0),
        ])->all();
    }

    /**
     * Open workload per team — impossible before tasks could belong to one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function workloadByTeam(): array
    {
        $doneKeys = $this->doneStatusKeys();

        return DB::table('tasks')
            ->join('teams', 'teams.id', '=', 'tasks.team_id')
            ->whereNull('tasks.deleted_at')
            ->whereNull('tasks.parent_task_id')
            ->whereNull('tasks.archived_at')
            ->whereNull('tasks.completed_at')
            ->whereNotIn('tasks.status', $doneKeys ?: ['__none__'])
            ->groupBy('teams.id', 'teams.name', 'teams.color')
            ->orderByDesc(DB::raw('COUNT(tasks.id)'))
            ->get([
                'teams.id',
                'teams.name',
                'teams.color',
                DB::raw('COUNT(tasks.id) as open_tasks'),
                DB::raw('COALESCE(SUM(tasks.estimate_minutes), 0) as estimated_minutes'),
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'color' => $r->color,
                'open_tasks' => (int) $r->open_tasks,
                'estimated_minutes' => (int) $r->estimated_minutes,
            ])
            ->all();
    }

    /**
     * Completion counts per user, read from task data rather than the audit log.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topCompleters(Carbon $from, int $limit = 8): array
    {
        return DB::table('tasks')
            ->join('users', 'users.id', '=', 'tasks.assignee_id')
            ->whereNull('tasks.deleted_at')
            ->whereNull('tasks.parent_task_id')
            ->whereNotNull('tasks.completed_at')
            ->where('tasks.completed_at', '>=', $from)
            ->groupBy('users.id', 'users.name', 'users.avatar', 'users.job_title')
            ->orderByDesc(DB::raw('COUNT(tasks.id)'))
            ->limit($limit)
            ->get([
                'users.id',
                'users.name',
                'users.avatar',
                'users.job_title',
                DB::raw('COUNT(tasks.id) as completed_tasks'),
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'initials' => collect(explode(' ', trim((string) $r->name)))
                    ->filter()->take(2)
                    ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                    ->implode(''),
                'job_title' => $r->job_title,
                'completed_tasks' => (int) $r->completed_tasks,
            ])
            ->all();
    }

    /**
     * Percentage of open work that is past its due date.
     */
    public function overdueRate(?User $scope = null): float
    {
        $open = (clone $this->base($scope))->whereNull('completed_at');
        $total = (clone $open)->count();

        if ($total === 0) {
            return 0.0;
        }

        $overdue = (clone $open)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();

        return round($overdue / $total * 100, 1);
    }

    /**
     * Root, non-archived, non-deleted tasks — the consistent base for every metric.
     */
    private function base(?User $scope = null)
    {
        $query = Task::query()->root()->notArchived();

        return $scope ? $query->visibleTo($scope) : $query;
    }

    /**
     * @return array<int, string>
     */
    private function doneStatusKeys(): array
    {
        return WorkflowStatus::query()
            ->where('category', WorkflowStatus::CATEGORY_DONE)
            ->distinct()
            ->pluck('key')
            ->all();
    }
}
