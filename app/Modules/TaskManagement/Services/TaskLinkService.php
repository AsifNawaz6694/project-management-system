<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskLink;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskLinkService
{
    /**
     * Link two tasks, writing both directions so each side reads naturally.
     */
    public function link(Task $source, Task $target, string $type, User $actor): TaskLink
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages([
                'target_task_id' => 'A task cannot be linked to itself.',
            ]);
        }

        if (! in_array($type, TaskLink::ALL, true)) {
            throw ValidationException::withMessages([
                'type' => 'Unknown link type.',
            ]);
        }

        // Only blocking edges can deadlock a board, so only those are cycle-checked.
        if (TaskLink::isBlocking($type) && $this->wouldCreateCycle($source->id, $target->id)) {
            throw ValidationException::withMessages([
                'target_task_id' => 'That link would create a circular dependency.',
            ]);
        }

        if ($type === TaskLink::BLOCKS && $this->wouldCreateCycle($target->id, $source->id)) {
            throw ValidationException::withMessages([
                'target_task_id' => 'That link would create a circular dependency.',
            ]);
        }

        return DB::transaction(function () use ($source, $target, $type, $actor) {
            $link = TaskLink::query()->firstOrCreate([
                'source_task_id' => $source->id,
                'target_task_id' => $target->id,
                'type' => $type,
            ], ['created_by_id' => $actor->id]);

            TaskLink::query()->firstOrCreate([
                'source_task_id' => $target->id,
                'target_task_id' => $source->id,
                'type' => TaskLink::inverseOf($type),
            ], ['created_by_id' => $actor->id]);

            Activity::logFor('task.linked', Activity::SUBJECT_TASK, $source->id, [
                'module' => 'tasks',
                'description' => "Linked {$source->key_label} ".TaskLink::LABELS[$type]." {$target->key_label}",
                'properties' => [
                    'task_id' => $source->id,
                    'target_task_id' => $target->id,
                    'type' => $type,
                ],
            ]);

            return $link;
        });
    }

    public function unlink(TaskLink $link, User $actor): void
    {
        DB::transaction(function () use ($link) {
            TaskLink::query()
                ->where('source_task_id', $link->target_task_id)
                ->where('target_task_id', $link->source_task_id)
                ->where('type', TaskLink::inverseOf($link->type))
                ->delete();

            $sourceId = $link->source_task_id;
            $targetId = $link->target_task_id;
            $link->delete();

            Activity::logFor('task.unlinked', Activity::SUBJECT_TASK, $sourceId, [
                'module' => 'tasks',
                'description' => "Removed a link between task #{$sourceId} and #{$targetId}",
                'properties' => ['task_id' => $sourceId, 'target_task_id' => $targetId],
            ]);
        });
    }

    /**
     * Would "$from blocked_by $to" close a loop?
     *
     * Walks the blocked_by graph outward from $to looking for $from. Iterative
     * and visited-tracked so a malformed graph cannot recurse forever.
     */
    private function wouldCreateCycle(int $from, int $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $visited = [];
        $frontier = [$to];

        while ($frontier !== []) {
            $next = TaskLink::query()
                ->whereIn('source_task_id', $frontier)
                ->where('type', TaskLink::BLOCKED_BY)
                ->pluck('target_task_id')
                ->all();

            $frontier = [];

            foreach ($next as $id) {
                $id = (int) $id;

                if ($id === $from) {
                    return true;
                }

                if (! isset($visited[$id])) {
                    $visited[$id] = true;
                    $frontier[] = $id;
                }
            }

            // Hard stop: no legitimate dependency chain is this deep.
            if (count($visited) > 5000) {
                break;
            }
        }

        return false;
    }

    /**
     * Links for a task, grouped by type and shaped for the detail page.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function forTask(Task $task, array $doneStatusKeys): array
    {
        $links = TaskLink::query()
            ->where('source_task_id', $task->id)
            ->with(['target:id,number,project_id,title,status,priority,assignee_id'])
            ->get();

        $projectKeys = Project::query()
            ->whereIn('id', $links->pluck('target.project_id')->filter()->unique())
            ->pluck('key', 'id');

        $grouped = [];

        foreach ($links as $link) {
            if (! $link->target) {
                continue;
            }

            $prefix = $projectKeys[$link->target->project_id] ?? null;

            $grouped[$link->type][] = [
                'link_id' => $link->id,
                'id' => $link->target->id,
                'key' => $prefix ? $prefix.'-'.$link->target->number : '#'.$link->target->id,
                'title' => $link->target->title,
                'status' => $link->target->status,
                'priority' => $link->target->priority,
                'is_done' => in_array($link->target->status, $doneStatusKeys, true),
            ];
        }

        return $grouped;
    }
}
