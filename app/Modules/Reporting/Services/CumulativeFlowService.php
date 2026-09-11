<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cumulative flow diagram: how many items sat in each stage on each day.
 *
 * `task_status_history` records every move with the status it moved *to*, so a
 * task's stage on any past day is the last transition on or before that day.
 * MySQL 5.7 has no window functions, so rather than asking the database for a
 * per-day snapshot, this walks each task's transitions once in PHP and fills a
 * day-by-stage grid. One query, linear in the number of transitions.
 */
class CumulativeFlowService
{
    /** Beyond this the chart is unreadable and the query is pointless. */
    public const MAX_DAYS = 180;

    /**
     * @return array{days: array<int, string>, stages: array<int, array{key: string, name: string, color: string}>, series: array<int, array<string, mixed>>}
     */
    public function build(User $user, int $days = 30, ?Project $project = null): array
    {
        $days = max(2, min($days, self::MAX_DAYS));
        $from = now()->subDays($days - 1)->startOfDay();

        $stages = $this->stages($project);
        $dayKeys = $this->dayKeys($from, $days);

        $taskIds = Task::query()
            ->visibleTo($user)
            ->root()
            ->notArchived()
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            // A task created after the window cannot appear in it.
            ->where('created_at', '<=', now())
            ->limit(5000)
            ->pluck('tasks.id');

        if ($taskIds->isEmpty()) {
            return ['days' => $dayKeys, 'stages' => $stages, 'series' => $this->emptySeries($dayKeys, $stages)];
        }

        // Every transition for those tasks, oldest first, plus the task's own
        // creation date so a task that never moved is still placed somewhere.
        $history = DB::table('task_status_history')
            ->whereIn('task_id', $taskIds)
            ->orderBy('task_id')
            ->orderBy('created_at')
            ->get(['task_id', 'to_status', 'created_at']);

        $created = Task::query()
            ->whereIn('id', $taskIds)
            ->pluck('created_at', 'id');

        $initial = $this->initialStatuses($taskIds);

        // task id => [day => status], built by carrying the last known status
        // forward across the window.
        $grid = array_fill_keys($dayKeys, []);
        $byTask = $history->groupBy('task_id');

        foreach ($taskIds as $taskId) {
            $moves = ($byTask[$taskId] ?? collect())
                ->map(fn ($row) => ['on' => Carbon::parse($row->created_at)->toDateString(), 'to' => $row->to_status])
                ->values();

            $bornOn = optional($created[$taskId] ?? null)->toDateString();
            $current = $initial[$taskId] ?? null;
            $pointer = 0;

            foreach ($dayKeys as $day) {
                // Apply every move that happened on or before this day.
                while ($pointer < $moves->count() && $moves[$pointer]['on'] <= $day) {
                    $current = $moves[$pointer]['to'];
                    $pointer++;
                }

                // Before it existed, it counts nowhere.
                if ($bornOn !== null && $bornOn > $day) {
                    continue;
                }

                if ($current !== null) {
                    $grid[$day][$current] = ($grid[$day][$current] ?? 0) + 1;
                }
            }
        }

        $series = [];

        foreach ($dayKeys as $day) {
            $row = ['date' => $day, 'label' => Carbon::parse($day)->format('j M')];

            foreach ($stages as $stage) {
                $row[$stage['key']] = $grid[$day][$stage['key']] ?? 0;
            }

            $series[] = $row;
        }

        return ['days' => $dayKeys, 'stages' => $stages, 'series' => $series];
    }

    /**
     * The status each task started in — its earliest recorded `from_status`,
     * falling back to its current status when it has no history at all.
     *
     * @param  Collection<int, int>  $taskIds
     * @return array<int, string>
     */
    private function initialStatuses($taskIds): array
    {
        $firstFrom = DB::table('task_status_history')
            ->whereIn('task_id', $taskIds)
            ->whereNotNull('from_status')
            ->orderBy('task_id')
            ->orderBy('created_at')
            ->get(['task_id', 'from_status'])
            ->groupBy('task_id')
            ->map(fn ($rows) => $rows->first()->from_status);

        $current = Task::query()->whereIn('id', $taskIds)->pluck('status', 'id');

        $out = [];

        foreach ($taskIds as $id) {
            $out[$id] = $firstFrom[$id] ?? $current[$id] ?? null;
        }

        return $out;
    }

    /**
     * @return array<int, array{key: string, name: string, color: string}>
     */
    private function stages(?Project $project): array
    {
        $query = WorkflowStatus::query()->orderBy('position');

        if ($project?->workflow_id) {
            $query->where('workflow_id', $project->workflow_id);
        }

        return $query->get(['key', 'name', 'color', 'position'])
            ->unique('key')
            ->values()
            ->map(fn (WorkflowStatus $s) => ['key' => $s->key, 'name' => $s->name, 'color' => $s->color])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function dayKeys(Carbon $from, int $days): array
    {
        $keys = [];

        for ($i = 0; $i < $days; $i++) {
            $keys[] = $from->copy()->addDays($i)->toDateString();
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $dayKeys
     * @param  array<int, array{key: string, name: string, color: string}>  $stages
     * @return array<int, array<string, mixed>>
     */
    private function emptySeries(array $dayKeys, array $stages): array
    {
        return array_map(fn (string $day) => array_merge(
            ['date' => $day, 'label' => Carbon::parse($day)->format('j M')],
            array_fill_keys(array_column($stages, 'key'), 0),
        ), $dayKeys);
    }
}
