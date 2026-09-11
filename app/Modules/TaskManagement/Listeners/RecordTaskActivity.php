<?php

namespace App\Modules\TaskManagement\Listeners;

use App\Modules\TaskManagement\Events\TaskCreated;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Events\TaskUpdated;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskStatusHistory;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Services\WorkflowService;

/**
 * Writes the audit trail and the status-history rows that flow metrics
 * (cycle time, lead time, cumulative flow) are computed from.
 */
class RecordTaskActivity
{
    /**
     * Field names as a reader would say them.
     */
    private const LABELS = [
        'title' => 'title',
        'description' => 'description',
        'priority' => 'priority',
        'due_date' => 'due date',
        'start_date' => 'start date',
        'estimate_minutes' => 'estimate',
        'assignee_id' => 'assignee',
        'team_id' => 'team',
        'task_type_id' => 'type',
        'story_points' => 'story points',
        'sprint_id' => 'sprint',
    ];

    public function __construct(private readonly WorkflowService $workflows) {}

    public function handleCreated(TaskCreated $event): void
    {
        $task = $event->task;

        Activity::logFor('task.created', Activity::SUBJECT_TASK, $task->id, [
            'user_id' => $event->actor->id,
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Created {$task->key_label}: {$task->title}",
            'properties' => [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'key' => $task->key_label,
            ],
        ]);

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $event->actor->id,
            'from_status' => null,
            'to_status' => $task->status,
            'duration_seconds' => null,
            'created_at' => now(),
        ]);
    }

    public function handleUpdated(TaskUpdated $event): void
    {
        $task = $event->task;

        // logChanges writes nothing when the diff is empty, so a save that
        // changed nothing can never reach the log.
        Activity::logChanges('task.updated', Activity::SUBJECT_TASK, $task->id, $event->changes, [
            'user_id' => $event->actor?->id,
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => $this->describeChanges($task, $event->changes),
            'properties' => ['project_id' => $task->project_id],
        ]);
    }

    public function handleStatusChanged(TaskStatusChanged $event): void
    {
        $task = $event->task;

        Activity::logFor($this->statusAction($event), Activity::SUBJECT_TASK, $task->id, [
            'user_id' => $event->actor?->id,
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Moved {$task->key_label} from {$event->from} to {$event->to}"
                .($event->reason ? ' — '.$event->reason : ''),
            'properties' => [
                'project_id' => $task->project_id,
                'from' => $event->from,
                'to' => $event->to,
                'reason' => $event->reason,
            ],
        ]);

        // Close out the previous interval so time-in-status is a stored sum.
        $previous = TaskStatusHistory::query()
            ->where('task_id', $task->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $now = now();
        $duration = $previous?->created_at
            ? max(0, $now->diffInSeconds($previous->created_at, absolute: true))
            : null;

        TaskStatusHistory::create([
            'task_id' => $task->id,
            'user_id' => $event->actor?->id,
            'from_status' => $event->from,
            'to_status' => $event->to,
            // The QA verdict (or block/reopen reason) lives with the transition
            // it explains, so the trail stays queryable.
            'note' => $event->reason,
            'duration_seconds' => $duration,
            'created_at' => $now,
        ]);
    }

    /**
     * Completion and reopening are distinct facts, not just another move.
     *
     * A timeline reporting "status changed" for both would make the two moments
     * that actually matter — work finishing, and finished work coming back —
     * indistinguishable from routine progress.
     */
    private function statusAction(TaskStatusChanged $event): string
    {
        if ($event->completed) {
            return 'task.completed';
        }

        return $event->from !== null && in_array($event->from, $this->workflows->doneStatusKeys(), true)
            ? 'task.reopened'
            : 'task.status-changed';
    }

    /**
     * Names the fields that moved, so the line reads on its own while the
     * structured before/after stays available underneath it.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     */
    private function describeChanges(Task $task, array $changes): string
    {
        $fields = array_map(
            fn (string $field) => self::LABELS[$field] ?? str_replace('_', ' ', $field),
            array_keys($changes),
        );

        return "Updated {$task->key_label}: ".implode(', ', $fields);
    }
}
