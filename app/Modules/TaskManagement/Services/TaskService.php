<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TimeLog;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(array $data, User $actor, array $files = []): Task
    {
        return DB::transaction(function () use ($data, $actor, $files) {
            $position = (int) Task::where('project_id', $data['project_id'])
                ->where('status', $data['status'] ?? 'todo')
                ->whereNull('parent_task_id')
                ->max('position');

            $task = Task::create([
                'project_id' => $data['project_id'],
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by_id' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'estimate_minutes' => $data['estimate_minutes'] ?? null,
                'position' => $position + 1,
                'completed_at' => ($data['status'] ?? null) === 'completed' ? now() : null,
            ]);

            $this->replaceSubtasks($task, $data['subtasks'] ?? [], $actor);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $this->attachFile($task, $file, $actor);
                }
            }

            Activity::log('task.created', [
                'subject_user_id' => $task->assignee_id,
                'module' => 'tasks',
                'description' => "Created task: {$task->title}".(count($files) ? ' with '.count($files).' attachment(s)' : ''),
                'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id, 'attachment_count' => count($files)],
            ]);

            if ($task->assignee_id) {
                $this->notifications->push((int) $task->assignee_id, [
                    'group' => Notification::GROUP_TASKS,
                    'type' => 'task.assigned',
                    'title' => "{$actor->name} assigned a task to you",
                    'body' => $task->title,
                    'icon' => 'list-checks',
                    'tone' => 'violet',
                    'link' => route('tasks.show', $task->id, false),
                    'data' => ['task_id' => $task->id],
                ], $actor->id);
            }

            return $task->load(['assignee', 'creator', 'subtasks']);
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $statusChanged = isset($data['status']) && $data['status'] !== $task->status;
            $oldStatus = $task->status;
            $oldAssignee = $task->assignee_id;

            $task->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ], fn ($v) => $v !== null));

            if (array_key_exists('estimate_minutes', $data)) {
                $task->estimate_minutes = $data['estimate_minutes'];
            }

            if (array_key_exists('assignee_id', $data)) {
                $task->assignee_id = $data['assignee_id'];
            }

            if (isset($data['status'])) {
                $task->status = $data['status'];
                $task->completed_at = $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null;
            }

            $task->save();

            if (array_key_exists('subtasks', $data)) {
                $this->replaceSubtasks($task, $data['subtasks'], $actor);
            }

            if ($statusChanged) {
                Activity::log('task.status-changed', [
                    'subject_user_id' => $task->assignee_id,
                    'module' => 'tasks',
                    'description' => "Moved \"{$task->title}\" from {$oldStatus} to {$task->status}",
                    'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id, 'from' => $oldStatus, 'to' => $task->status],
                ]);
            } else {
                Activity::log('task.updated', [
                    'subject_user_id' => $task->assignee_id,
                    'module' => 'tasks',
                    'description' => "Updated task: {$task->title}",
                    'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
                ]);
            }

            if ($task->assignee_id && $task->assignee_id !== $oldAssignee) {
                $this->notifications->push((int) $task->assignee_id, [
                    'group' => Notification::GROUP_TASKS,
                    'type' => 'task.assigned',
                    'title' => "{$actor->name} assigned a task to you",
                    'body' => $task->title,
                    'icon' => 'list-checks',
                    'tone' => 'violet',
                    'link' => route('tasks.show', $task->id, false),
                    'data' => ['task_id' => $task->id],
                ], $actor->id);
            }

            return $task->load(['assignee', 'creator', 'subtasks']);
        });
    }

    public function changeStatus(Task $task, string $status, ?int $position = null): Task
    {
        return DB::transaction(function () use ($task, $status, $position) {
            $oldStatus = $task->status;

            if ($oldStatus !== $status) {
                $task->status = $status;
                $task->completed_at = $status === 'completed' ? now() : null;
            }

            if ($position !== null) {
                $task->position = $position;
            }

            $task->save();

            $this->resequenceColumn($task->project_id, $status);

            if ($oldStatus !== $status) {
                Activity::log('task.status-changed', [
                    'subject_user_id' => $task->assignee_id,
                    'module' => 'tasks',
                    'description' => "Moved \"{$task->title}\" from {$oldStatus} to {$status}",
                    'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id, 'from' => $oldStatus, 'to' => $status],
                ]);
            }

            return $task->refresh();
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            foreach ($task->attachments as $attachment) {
                $attachment->deleteFile();
            }
            $title = $task->title;
            $task->delete();
            Activity::log('task.deleted', [
                'module' => 'tasks',
                'description' => "Deleted task: {$title}",
                'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
            ]);
        });
    }

    public function attachFile(Task $task, UploadedFile $file, User $uploader)
    {
        $path = $file->store("task-attachments/{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'uploader_id' => $uploader->id,
            'file_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        Activity::log('task.attachment-added', [
            'module' => 'tasks',
            'description' => "Attached {$file->getClientOriginalName()} to {$task->title}",
            'properties' => ['task_id' => $task->id, 'attachment_id' => $attachment->id],
        ]);

        return $attachment;
    }

    public function downloadAttachment($attachment)
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name);
    }

    public function deleteAttachment($attachment): void
    {
        $name = $attachment->file_name;
        $taskId = $attachment->task_id;
        $attachment->deleteFile();
        $attachment->delete();

        Activity::log('task.attachment-removed', [
            'module' => 'tasks',
            'description' => "Removed attachment {$name} from task #{$taskId}",
            'properties' => ['task_id' => $taskId, 'attachment_id' => $attachment->id],
        ]);
    }

    public function addComment(Task $task, User $user, string $body)
    {
        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        Activity::log('task.commented', [
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Commented on {$task->title}",
            'properties' => ['task_id' => $task->id, 'comment_id' => $comment->id],
        ]);

        return $comment->load('user');
    }

    public function logTime(Task $task, User $user, array $data): TimeLog
    {
        return DB::transaction(function () use ($task, $user, $data) {
            $log = $task->timeLogs()->create([
                'user_id' => $user->id,
                'minutes' => (int) $data['minutes'],
                'started_at' => $data['started_at'],
                'note' => $data['note'] ?? null,
            ]);

            Activity::log('task.time-logged', [
                'subject_user_id' => $task->assignee_id,
                'module' => 'tasks',
                'description' => "Logged {$this->formatMinutes((int) $data['minutes'])} on \"{$task->title}\"",
                'properties' => [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'time_log_id' => $log->id,
                    'minutes' => (int) $data['minutes'],
                ],
            ]);

            return $log->load('user:id,name,avatar');
        });
    }

    public function deleteTimeLog(TimeLog $log): void
    {
        DB::transaction(function () use ($log) {
            $task = $log->task;
            $minutes = $log->minutes;
            $log->delete();

            Activity::log('task.time-log-removed', [
                'module' => 'tasks',
                'description' => "Removed time log of {$this->formatMinutes($minutes)} from \"{$task->title}\"",
                'properties' => [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'minutes' => $minutes,
                ],
            ]);
        });
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $rem === 0 ? "{$hours}h" : "{$hours}h {$rem}m";
    }

    private function replaceSubtasks(Task $task, array $subtasks, User $actor): void
    {
        $task->subtasks()->delete();
        foreach (array_values($subtasks) as $i => $sub) {
            if (empty($sub['title'])) {
                continue;
            }

            $task->subtasks()->create([
                'project_id' => $task->project_id,
                'created_by_id' => $actor->id,
                'assignee_id' => $sub['assignee_id'] ?? null,
                'title' => $sub['title'],
                'description' => $sub['description'] ?? null,
                'priority' => $sub['priority'] ?? $task->priority,
                'status' => ! empty($sub['completed']) ? 'completed' : ($sub['status'] ?? 'todo'),
                'due_date' => $sub['due_date'] ?? null,
                'position' => $i,
                'completed_at' => ! empty($sub['completed']) ? now() : null,
            ]);
        }
    }

    private function resequenceColumn(int $projectId, string $status): void
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('status', $status)
            ->whereNull('parent_task_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $i => $t) {
            if ($t->position !== $i + 1) {
                $t->update(['position' => $i + 1]);
            }
        }
    }
}
