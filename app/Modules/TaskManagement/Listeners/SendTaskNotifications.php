<?php

namespace App\Modules\TaskManagement\Listeners;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Events\TaskAssigned;
use App\Modules\TaskManagement\Events\TaskCommented;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskLink;
use Illuminate\Support\Str;

/**
 * Every task notification lives here, so the recipient rules are in one place
 * instead of scattered through the service layer.
 */
class SendTaskNotifications
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handleAssigned(TaskAssigned $event): void
    {
        $task = $event->task;

        if ($event->assigneeId) {
            $this->notifications->push($event->assigneeId, [
                'group' => Notification::GROUP_TASKS,
                'type' => 'task.assigned',
                'title' => "{$event->actor->name} assigned a task to you",
                'body' => $task->key_label.' · '.$task->title,
                'icon' => 'list-checks',
                'tone' => 'blue',
                'link' => route('tasks.show', $task->id, false),
                'data' => ['task_id' => $task->id],
            ], $event->actor->id);
        }

        if ($event->teamId) {
            $memberIds = $this->teamMemberIds($event->teamId, $event->assigneeId);

            $this->notifications->push($memberIds, [
                'group' => Notification::GROUP_TASKS,
                'type' => 'task.assigned-team',
                'title' => "{$event->actor->name} assigned work to your team",
                'body' => $task->key_label.' · '.$task->title,
                'icon' => 'users-round',
                'tone' => 'blue',
                'link' => route('tasks.show', $task->id, false),
                'data' => ['task_id' => $task->id, 'team_id' => $event->teamId],
            ], $event->actor->id);
        }
    }

    public function handleStatusChanged(TaskStatusChanged $event): void
    {
        $task = $event->task;
        $actorId = $event->actor?->id;
        $actorName = $event->actor?->name ?? 'Someone';

        $recipients = $this->interestedUsers($task, $actorId);

        if ($recipients !== []) {
            $this->notifications->push($recipients, [
                'group' => Notification::GROUP_TASKS,
                'type' => $event->completed ? 'task.completed' : 'task.status-changed',
                'title' => $event->completed
                    ? "{$actorName} completed a task you follow"
                    : "{$actorName} moved a task you follow",
                'body' => $task->key_label.' · '.$task->title,
                'icon' => $event->completed ? 'check-circle-2' : 'refresh-cw',
                'tone' => $event->completed ? 'emerald' : 'blue',
                'link' => route('tasks.show', $task->id, false),
                'data' => [
                    'task_id' => $task->id,
                    'from' => $event->from,
                    'to' => $event->to,
                ],
            ], $actorId);
        }

        // Unblock anything waiting on this task once it closes.
        if ($event->completed) {
            $this->notifyUnblocked($task, $actorId, $actorName);
        }
    }

    public function handleCommented(TaskCommented $event): void
    {
        $task = $event->task;

        // @mentions are notified separately by MentionParser; don't double-notify.
        $recipients = array_values(array_diff(
            $this->interestedUsers($task, $event->actor->id),
            $event->mentioned,
        ));

        if ($recipients === []) {
            return;
        }

        $this->notifications->push($recipients, [
            'group' => Notification::GROUP_TASKS,
            'type' => 'task.commented',
            'title' => "{$event->actor->name} commented on a task you follow",
            'body' => Str::limit($event->comment->body, 120),
            'icon' => 'message-square-text',
            'tone' => 'blue',
            'link' => route('tasks.show', $task->id, false),
            'data' => ['task_id' => $task->id, 'comment_id' => $event->comment->id],
        ], $event->actor->id);
    }

    /**
     * Assignee + reporter + watchers, minus the actor.
     *
     * @return array<int, int>
     */
    private function interestedUsers(Task $task, ?int $actorId): array
    {
        $ids = [$task->assignee_id, $task->created_by_id];

        $ids = array_merge($ids, $task->watchers()->pluck('users.id')->all());

        if ($task->team_id) {
            $ids = array_merge($ids, $this->teamMemberIds($task->team_id, null));
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $actorId ? array_values(array_diff($ids, [$actorId])) : $ids;
    }

    /**
     * @return array<int, int>
     */
    private function teamMemberIds(int $teamId, ?int $exclude): array
    {
        $ids = User::query()
            ->whereExists(function ($q) use ($teamId) {
                $q->selectRaw('1')
                    ->from('team_user')
                    ->whereColumn('team_user.user_id', 'users.id')
                    ->where('team_user.team_id', $teamId);
            })
            ->pluck('id')
            ->all();

        return $exclude ? array_values(array_diff($ids, [$exclude])) : $ids;
    }

    /**
     * Tell owners of tasks that were blocked by this one that they are now clear.
     */
    private function notifyUnblocked(Task $task, ?int $actorId, string $actorName): void
    {
        $blocked = Task::query()
            ->whereIn('id', function ($q) use ($task) {
                $q->select('source_task_id')
                    ->from('task_links')
                    ->where('target_task_id', $task->id)
                    ->where('type', TaskLink::BLOCKED_BY);
            })
            ->get(['id', 'title', 'assignee_id', 'created_by_id', 'project_id', 'number']);

        foreach ($blocked as $b) {
            $ids = array_values(array_unique(array_filter([$b->assignee_id, $b->created_by_id])));
            if ($actorId) {
                $ids = array_values(array_diff($ids, [$actorId]));
            }
            if ($ids === []) {
                continue;
            }

            $this->notifications->push($ids, [
                'group' => Notification::GROUP_TASKS,
                'type' => 'task.unblocked',
                'title' => "{$actorName} cleared a blocker",
                'body' => $b->key_label.' · '.$b->title.' is no longer blocked',
                'icon' => 'unlock',
                'tone' => 'emerald',
                'link' => route('tasks.show', $b->id, false),
                'data' => ['task_id' => $b->id, 'blocker_id' => $task->id],
            ], $actorId);
        }
    }
}
