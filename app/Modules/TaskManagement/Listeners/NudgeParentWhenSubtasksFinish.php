<?php

namespace App\Modules\TaskManagement\Listeners;

use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\SubtaskRollupService;

/**
 * Tells a parent's owner when the last piece of its work lands.
 *
 * Deliberately a nudge rather than an automatic transition: closing someone
 * else's task without asking is exactly the kind of surprise that makes people
 * stop trusting a board. The parent's own workflow rules still decide when and
 * how it may move.
 */
class NudgeParentWhenSubtasksFinish
{
    public function __construct(
        private readonly SubtaskRollupService $rollup,
        private readonly NotificationService $notifications,
    ) {}

    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if (! $task->parent_task_id) {
            return;
        }

        $parent = Task::query()->find($task->parent_task_id);

        if (! $parent || ! $this->rollup->allChildrenDone($parent)) {
            return;
        }

        // The parent may already be finished, in which case there is nothing
        // to nudge anyone about.
        if ($this->isDone($parent)) {
            return;
        }

        $recipients = array_filter([$parent->assignee_id, $parent->created_by_id]);

        if ($recipients === []) {
            return;
        }

        $this->notifications->push($recipients, [
            'group' => Notification::GROUP_TASKS,
            'type' => 'task.subtasks-complete',
            'title' => 'Every sub-task is finished',
            'body' => $parent->key_label.' · '.$parent->title.' is ready to close',
            'icon' => 'check-circle-2',
            'tone' => 'emerald',
            'link' => route('tasks.show', $parent, false),
            'data' => ['task_id' => $parent->id],
        ], $event->actor?->id);
    }

    private function isDone(Task $parent): bool
    {
        return $parent->completed_at !== null;
    }
}
