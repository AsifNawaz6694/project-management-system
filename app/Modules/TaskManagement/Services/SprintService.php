<?php

namespace App\Modules\TaskManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Sprint;
use App\Modules\TaskManagement\Models\SprintSnapshot;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintService
{
    /** @var array<int, string>|null */
    private ?array $doneKeys = null;

    public function __construct(private readonly WorkflowService $workflows) {}

    public function create(Project $project, array $data): Sprint
    {
        return DB::transaction(function () use ($project, $data) {
            $sprint = Sprint::query()->create([
                'project_id' => $project->id,
                'name' => $data['name'],
                'goal' => $data['goal'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'state' => Sprint::STATE_FUTURE,
                'position' => (int) Sprint::query()->where('project_id', $project->id)->max('position') + 1,
            ]);

            Activity::logFor('sprints.created', Activity::SUBJECT_PROJECT, $project->id, [
                'module' => 'tasks',
                'description' => "Created sprint \"{$sprint->name}\"",
                'properties' => ['sprint_id' => $sprint->id],
            ]);

            return $sprint;
        });
    }

    public function update(Sprint $sprint, array $data): Sprint
    {
        $sprint->update(array_intersect_key($data, array_flip(['name', 'goal', 'starts_at', 'ends_at'])));

        return $sprint;
    }

    /**
     * Starts a sprint. A project runs at most one sprint at a time — the point
     * of a sprint is that it is the single thing in flight.
     */
    public function start(Sprint $sprint): Sprint
    {
        if ($sprint->state === Sprint::STATE_COMPLETED) {
            throw ValidationException::withMessages(['sprint' => 'A completed sprint cannot be restarted.']);
        }

        $running = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->where('state', Sprint::STATE_ACTIVE)
            ->where('id', '!=', $sprint->id)
            ->value('name');

        if ($running) {
            throw ValidationException::withMessages([
                'sprint' => "\"{$running}\" is still running. Complete it before starting another.",
            ]);
        }

        if ($sprint->tasks()->count() === 0) {
            throw ValidationException::withMessages(['sprint' => 'Add some work to the sprint before starting it.']);
        }

        $sprint->forceFill([
            'state' => Sprint::STATE_ACTIVE,
            'started_at' => now(),
            'starts_at' => $sprint->starts_at ?? now()->toDateString(),
        ])->save();

        // Day zero, so the burndown has a starting height even if the sprint is
        // completed before the nightly job ever runs.
        $this->snapshot($sprint);

        Activity::logFor('sprints.started', Activity::SUBJECT_PROJECT, $sprint->project_id, [
            'module' => 'tasks',
            'description' => "Started sprint \"{$sprint->name}\"",
            'properties' => ['sprint_id' => $sprint->id],
        ]);

        return $sprint;
    }

    /**
     * Completes a sprint and decides what happens to work that did not finish.
     *
     * @param  'backlog'|'next'  $moveTo  where unfinished tasks go
     */
    public function complete(Sprint $sprint, string $moveTo = 'backlog', ?int $targetSprintId = null): Sprint
    {
        if ($sprint->state !== Sprint::STATE_ACTIVE) {
            throw ValidationException::withMessages(['sprint' => 'Only a running sprint can be completed.']);
        }

        return DB::transaction(function () use ($sprint, $moveTo, $targetSprintId) {
            $this->snapshot($sprint);

            $doneKeys = $this->doneKeys();

            $unfinished = Task::query()
                ->where('sprint_id', $sprint->id)
                ->whereNotIn('status', $doneKeys ?: ['__none__'])
                ->pluck('id');

            $destination = null;

            if ($moveTo === 'next') {
                $destination = $targetSprintId
                    ?: Sprint::query()
                        ->where('project_id', $sprint->project_id)
                        ->where('state', Sprint::STATE_FUTURE)
                        ->orderBy('position')
                        ->value('id');
            }

            if ($unfinished->isNotEmpty()) {
                Task::query()->whereIn('id', $unfinished)->update(['sprint_id' => $destination]);
            }

            $sprint->forceFill([
                'state' => Sprint::STATE_COMPLETED,
                'completed_at' => now(),
                'ends_at' => $sprint->ends_at ?? now()->toDateString(),
            ])->save();

            Activity::logFor('sprints.completed', Activity::SUBJECT_PROJECT, $sprint->project_id, [
                'module' => 'tasks',
                'description' => "Completed sprint \"{$sprint->name}\"",
                'properties' => [
                    'sprint_id' => $sprint->id,
                    'carried_over' => $unfinished->count(),
                    'moved_to' => $destination,
                ],
            ]);

            return $sprint;
        });
    }

    public function delete(Sprint $sprint): void
    {
        DB::transaction(function () use ($sprint) {
            // Never delete the work, only the container.
            Task::query()->where('sprint_id', $sprint->id)->update(['sprint_id' => null]);
            $sprint->delete();
        });
    }

    /**
     * Moves tasks into a sprint (or out of one when $sprintId is null).
     *
     * @param  array<int, int>  $taskIds
     */
    public function assign(?Sprint $sprint, array $taskIds, Project $project): int
    {
        $ids = array_filter(array_map('intval', $taskIds));

        if ($ids === []) {
            return 0;
        }

        // Only ever move work that belongs to this project, whatever was posted.
        return Task::query()
            ->whereIn('id', $ids)
            ->where('project_id', $project->id)
            ->update(['sprint_id' => $sprint?->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    /**
     * Records today's position for a sprint. Idempotent — running it twice in
     * one day overwrites rather than duplicates.
     */
    public function snapshot(Sprint $sprint, ?Carbon $date = null): SprintSnapshot
    {
        $date ??= now();
        $totals = $this->totals($sprint);

        return SprintSnapshot::query()->updateOrCreate(
            ['sprint_id' => $sprint->id, 'snapshot_date' => $date->toDateString()],
            [
                'remaining_points' => $totals['total_points'] - $totals['completed_points'],
                'completed_points' => $totals['completed_points'],
                'remaining_tasks' => $totals['total_tasks'] - $totals['completed_tasks'],
                'completed_tasks' => $totals['completed_tasks'],
            ],
        );
    }

    /**
     * @return array{total_points: float, completed_points: float, total_tasks: int, completed_tasks: int}
     */
    public function totals(Sprint $sprint): array
    {
        $doneKeys = $this->doneKeys();

        $rows = Task::query()
            ->where('sprint_id', $sprint->id)
            ->get(['status', 'story_points']);

        $completed = $rows->filter(fn (Task $t) => in_array($t->status, $doneKeys, true));

        return [
            'total_points' => round((float) $rows->sum('story_points'), 1),
            'completed_points' => round((float) $completed->sum('story_points'), 1),
            'total_tasks' => $rows->count(),
            'completed_tasks' => $completed->count(),
        ];
    }

    /**
     * Burndown: one point per calendar day of the sprint, plus the straight
     * "ideal" line teams compare against.
     *
     * @return array<int, array{date: string, remaining: float, ideal: float|null}>
     */
    public function burndown(Sprint $sprint): array
    {
        $start = $sprint->starts_at ?? $sprint->started_at ?? $sprint->created_at;
        $end = $sprint->ends_at ?? $sprint->completed_at ?? now();

        if (! $start) {
            return [];
        }

        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $snapshots = $sprint->snapshots->keyBy(fn (SprintSnapshot $s) => $s->snapshot_date->toDateString());
        $opening = (float) ($snapshots->first()?->remaining_points ?? $this->totals($sprint)['total_points']);

        $days = $start->diffInDays($end);
        $series = [];
        $carried = $opening;

        for ($i = 0; $i <= $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();

            if ($snapshots->has($key)) {
                $carried = (float) $snapshots[$key]->remaining_points;
            }

            $series[] = [
                'date' => $key,
                // A future day has no actual reading yet — the chart must break
                // the line there rather than draw a flat tail.
                'remaining' => $day->isAfter(now()->startOfDay()) ? null : round($carried, 1),
                'ideal' => $days > 0 ? round($opening - ($opening / $days * $i), 1) : 0.0,
            ];
        }

        return $series;
    }

    /**
     * Velocity: points delivered per completed sprint, most recent last.
     *
     * @return array<int, array{sprint: string, committed: float, completed: float}>
     */
    public function velocity(Project $project, int $limit = 8): array
    {
        $sprints = Sprint::query()
            ->where('project_id', $project->id)
            ->where('state', Sprint::STATE_COMPLETED)
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get();

        return $sprints->reverse()->map(function (Sprint $sprint) {
            // reorder() matters: the relation already sorts ascending, and a
            // second orderBy would be appended rather than replace it.
            $first = $sprint->snapshots()->reorder('snapshot_date')->first();
            $last = $sprint->snapshots()->reorder('snapshot_date', 'desc')->first();

            return [
                'sprint' => $sprint->name,
                // Committed is what the sprint held on day one, not what it
                // holds now — tasks pulled in mid-sprint must not flatter it.
                'committed' => round((float) (($first?->remaining_points ?? 0) + ($first?->completed_points ?? 0)), 1),
                'completed' => round((float) ($last?->completed_points ?? 0), 1),
            ];
        })->values()->all();
    }

    /**
     * Done keys across every workflow, so a project on a custom pipeline still
     * counts its own completion states.
     *
     * @return array<int, string>
     */
    private function doneKeys(): array
    {
        return $this->doneKeys ??= $this->workflows->doneStatusKeys();
    }

    /**
     * Snapshots every running sprint. Called nightly by the scheduler.
     */
    public function snapshotAllActive(): int
    {
        $count = 0;

        Sprint::query()->where('state', Sprint::STATE_ACTIVE)->chunkById(50, function ($sprints) use (&$count) {
            foreach ($sprints as $sprint) {
                $this->snapshot($sprint);
                $count++;
            }
        });

        return $count;
    }

    public function currentFor(Project $project): ?Sprint
    {
        return Sprint::query()
            ->where('project_id', $project->id)
            ->where('state', Sprint::STATE_ACTIVE)
            ->first();
    }

    public function assertOwnedBy(Sprint $sprint, Project $project): void
    {
        if ($sprint->project_id !== $project->id) {
            throw ValidationException::withMessages(['sprint' => 'That sprint belongs to another project.']);
        }
    }

    /**
     * @param  array<int, int>  $taskIds
     */
    public function setPoints(array $taskIds, ?float $points, Project $project): int
    {
        $ids = array_filter(array_map('intval', $taskIds));

        if ($ids === []) {
            return 0;
        }

        return Task::query()
            ->whereIn('id', $ids)
            ->where('project_id', $project->id)
            ->update(['story_points' => $points]);
    }
}
