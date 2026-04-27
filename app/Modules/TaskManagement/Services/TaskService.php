<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
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
                'position' => $position + 1,
                'completed_at' => ($data['status'] ?? null) === 'completed' ? now() : null,
            ]);

            $this->replaceSubtasks($task, $data['subtasks'] ?? [], $actor);

            Activity::log('task.created', [
                'subject_user_id' => $task->assignee_id,
                'module' => 'tasks',
                'description' => "Created task: {$task->title}",
                'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
            ]);

            return $task->load(['assignee', 'creator', 'subtasks']);
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $statusChanged = isset($data['status']) && $data['status'] !== $task->status;
            $oldStatus = $task->status;

            $task->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ], fn ($v) => $v !== null));

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
        $attachment->deleteFile();
        $attachment->delete();
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
