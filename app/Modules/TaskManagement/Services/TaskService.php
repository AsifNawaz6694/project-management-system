<?php

namespace App\Modules\TaskManagement\Services;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Events\TaskAssigned;
use App\Modules\TaskManagement\Events\TaskCreated;
use App\Modules\TaskManagement\Events\TaskStatusChanged;
use App\Modules\TaskManagement\Events\TaskUpdated;
use App\Modules\TaskManagement\Models\Label;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TimeLog;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Fields whose before/after values are recorded on update.
     */
    private const TRACKED = [
        'title', 'description', 'priority', 'due_date', 'start_date',
        'estimate_minutes', 'assignee_id', 'team_id', 'task_type_id',
        'story_points', 'sprint_id',
    ];

    /**
     * Fields that may legitimately be set back to null.
     */
    private const NULLABLE = [
        'description', 'due_date', 'start_date', 'estimate_minutes',
        'assignee_id', 'team_id', 'task_type_id', 'story_points', 'sprint_id',
    ];

    public function __construct(private readonly WorkflowService $workflows) {}

    // ------------------------------------------------------------------ create

    public function create(array $data, User $actor, array $files = []): Task
    {
        return DB::transaction(function () use ($data, $actor, $files) {
            $project = Project::query()->findOrFail($data['project_id']);
            $status = $data['status'] ?? $this->workflows->initialStatusKey($project);

            $this->assertStatusExists($project, $status);

            $position = (int) Task::query()
                ->where('project_id', $project->id)
                ->where('status', $status)
                ->whereNull('parent_task_id')
                ->max('position');

            $task = Task::create([
                'project_id' => $project->id,
                'task_type_id' => $data['task_type_id'] ?? null,
                'sprint_id' => $data['sprint_id'] ?? null,
                'story_points' => $data['story_points'] ?? null,
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'created_by_id' => $actor->id,
                'number' => $project->nextTaskNumber(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'estimate_minutes' => $data['estimate_minutes'] ?? null,
                'position' => $position + 1,
                'completed_at' => $this->isDone($project, $status) ? now() : null,
            ]);

            $task->setRelation('project', $project);

            $this->syncSubtasks($task, $data['subtasks'] ?? null, $actor);
            $this->syncLabels($task, $data['labels'] ?? null);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $this->attachFile($task, $file, $actor);
                }
            }

            // The reporter follows their own task by default; the assignee too.
            $this->addWatchers($task, array_filter([$actor->id, $task->assignee_id]));

            TaskCreated::dispatch($task, $actor);

            if ($task->assignee_id || $task->team_id) {
                TaskAssigned::dispatch($task, $actor, $task->assignee_id, $task->team_id);
            }

            return $task->load(['assignee', 'creator', 'subtasks', 'labels', 'type', 'team']);
        });
    }

    // ------------------------------------------------------------------ update

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $project = $task->project ?: Project::query()->findOrFail($task->project_id);
            $oldStatus = $task->status;
            $oldAssignee = $task->assignee_id;
            $oldTeam = $task->team_id;

            $changes = [];

            foreach (self::TRACKED as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $new = $data[$field];

                // Only nullable fields may be cleared; everything else ignores a null.
                if ($new === null && ! in_array($field, self::NULLABLE, true)) {
                    continue;
                }

                $current = $task->getAttribute($field);
                $currentScalar = $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current;
                $newScalar = $new instanceof \DateTimeInterface ? $new->format('Y-m-d') : $new;

                if ((string) $currentScalar !== (string) $newScalar) {
                    $changes[$field] = [$currentScalar, $newScalar];
                }

                $task->setAttribute($field, $new);
            }

            $statusChanged = false;
            if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== $oldStatus) {
                $this->assertStatusExists($project, $data['status']);
                $this->assertTransitionAllowed($task, $oldStatus, $data['status'], $actor);
                $this->assertReasonProvided($task, $oldStatus, $data['status'], $actor, $data['reason'] ?? null);

                $task->status = $data['status'];
                $task->completed_at = $this->isDone($project, $data['status'])
                    ? ($task->completed_at ?? now())
                    : null;
                $statusChanged = true;
            }

            $task->save();

            if (array_key_exists('subtasks', $data)) {
                $this->syncSubtasks($task, $data['subtasks'], $actor);
            }

            if (array_key_exists('labels', $data)) {
                $this->syncLabels($task, $data['labels']);
            }

            if ($changes !== []) {
                TaskUpdated::dispatch($task, $actor, $changes);
            }

            if ($statusChanged) {
                TaskStatusChanged::dispatch(
                    $task,
                    $actor,
                    $oldStatus,
                    $task->status,
                    $this->isDone($project, $task->status),
                    $data['reason'] ?? null,
                );
            }

            $assigneeChanged = $task->assignee_id && $task->assignee_id !== $oldAssignee;
            $teamChanged = $task->team_id && $task->team_id !== $oldTeam;

            if ($assigneeChanged || $teamChanged) {
                $this->addWatchers($task, array_filter([$task->assignee_id]));

                TaskAssigned::dispatch(
                    $task,
                    $actor,
                    $assigneeChanged ? $task->assignee_id : null,
                    $teamChanged ? $task->team_id : null,
                );
            }

            return $task->load(['assignee', 'creator', 'subtasks', 'labels', 'type', 'team']);
        });
    }

    // ------------------------------------------------------------------ status

    public function changeStatus(Task $task, string $status, ?int $position, ?User $actor, ?string $reason = null): Task
    {
        return DB::transaction(function () use ($task, $status, $position, $actor, $reason) {
            $project = $task->project ?: Project::query()->findOrFail($task->project_id);
            $oldStatus = $task->status;

            if ($oldStatus !== $status) {
                $this->assertStatusExists($project, $status);
                $this->assertTransitionAllowed($task, $oldStatus, $status, $actor);
                $this->assertReasonProvided($task, $oldStatus, $status, $actor, $reason);
                $this->assertNotBlocked($task, $project, $status);

                $task->status = $status;
                $task->completed_at = $this->isDone($project, $status) ? now() : null;
            }

            if ($position !== null) {
                $task->position = $position;
            }

            $task->save();
            $this->resequenceColumn($task->project_id, $status);

            if ($oldStatus !== $status) {
                TaskStatusChanged::dispatch(
                    $task,
                    $actor,
                    $oldStatus,
                    $status,
                    $this->isDone($project, $status),
                    $reason,
                );
            }

            return $task->refresh();
        });
    }

    /**
     * Refuse to close a task that still has an open blocker.
     */
    private function assertNotBlocked(Task $task, Project $project, string $status): void
    {
        if (! $this->isDone($project, $status)) {
            return;
        }

        if ($task->isBlocked($this->workflows->doneStatusKeys())) {
            throw ValidationException::withMessages([
                'status' => 'This task is blocked by another task that is not finished yet.',
            ]);
        }
    }

    private function assertStatusExists(Project $project, string $status): void
    {
        if (! in_array($status, $this->workflows->statusKeysFor($project), true)) {
            throw ValidationException::withMessages([
                'status' => "The status \"{$status}\" is not part of this project's workflow.",
            ]);
        }
    }

    /**
     * Some transitions demand an explanation — a QA verdict, a block, a reopen.
     * Refuse the move rather than recording an unexplained one.
     */
    private function assertReasonProvided(Task $task, ?string $from, string $to, ?User $actor, ?string $reason): void
    {
        $label = $this->workflows->requiredCommentLabel(
            $this->workflows->forTask($task),
            $from,
            $to,
            $actor,
        );

        if ($label === null) {
            return;
        }

        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages(['reason' => $label]);
        }
    }

    private function assertTransitionAllowed(Task $task, ?string $from, string $to, ?User $actor): void
    {
        $workflow = $this->workflows->forTask($task);

        if (! $this->workflows->canTransition($workflow, $from, $to, $actor)) {
            throw ValidationException::withMessages([
                'status' => "Moving from \"{$from}\" to \"{$to}\" is not allowed by this workflow.",
            ]);
        }
    }

    private function isDone(Project $project, string $status): bool
    {
        return $this->workflows->isDoneStatus($this->workflows->forProject($project), $status);
    }

    // ------------------------------------------------------------------ archive / delete

    public function archive(Task $task, User $actor): void
    {
        $task->update(['archived_at' => now()]);

        Activity::logFor('task.archived', Activity::SUBJECT_TASK, $task->id, [
            'user_id' => $actor->id,
            'module' => 'tasks',
            'description' => "Archived {$task->key_label}: {$task->title}",
            'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
        ]);
    }

    public function unarchive(Task $task, User $actor): void
    {
        $task->update(['archived_at' => null]);

        Activity::logFor('task.unarchived', Activity::SUBJECT_TASK, $task->id, [
            'user_id' => $actor->id,
            'module' => 'tasks',
            'description' => "Restored {$task->key_label} from the archive",
            'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
        ]);
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            foreach ($task->attachments as $attachment) {
                $attachment->deleteFile();
            }

            $label = $task->key_label;
            $title = $task->title;
            $task->delete();

            Activity::logFor('task.deleted', Activity::SUBJECT_TASK, $task->id, [
                'module' => 'tasks',
                'description' => "Deleted {$label}: {$title}",
                'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
            ]);
        });
    }

    public function restore(Task $task): void
    {
        $task->restore();

        Activity::logFor('task.restored', Activity::SUBJECT_TASK, $task->id, [
            'module' => 'tasks',
            'description' => "Restored {$task->key_label}: {$task->title}",
            'properties' => ['task_id' => $task->id, 'project_id' => $task->project_id],
        ]);
    }

    // ------------------------------------------------------------------ subtasks

    /**
     * Reconcile subtasks against the submitted set.
     *
     * Rows carrying an id are updated in place, new rows are inserted, and only
     * rows the client actually dropped are deleted. This replaces the previous
     * delete-everything-and-recreate behaviour, which destroyed subtask identity
     * (and anything attached to it) on every parent save.
     */
    private function syncSubtasks(Task $task, ?array $subtasks, User $actor): void
    {
        if ($subtasks === null) {
            return;
        }

        $existing = $task->subtasks()->get()->keyBy('id');
        $seen = [];
        $project = $task->project ?: Project::query()->find($task->project_id);
        $doneKey = $this->doneKeyFor($project);
        $todoKey = $this->workflows->initialStatusKey($project);

        foreach (array_values($subtasks) as $i => $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $completed = ! empty($row['completed']);
            $status = $row['status'] ?? ($completed ? $doneKey : $todoKey);

            $attributes = [
                'assignee_id' => $row['assignee_id'] ?? null,
                'title' => $title,
                'description' => $row['description'] ?? null,
                'priority' => $row['priority'] ?? $task->priority,
                'due_date' => $row['due_date'] ?? null,
                'position' => $i,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;
            $current = $id ? $existing->get($id) : null;

            if ($current) {
                $wasDone = $current->completed_at !== null;

                $current->fill($attributes);
                if ($current->status !== $status) {
                    $current->status = $status;
                    $current->completed_at = $completed ? ($current->completed_at ?? now()) : null;
                } elseif ($completed !== $wasDone) {
                    $current->completed_at = $completed ? now() : null;
                }
                $current->save();

                $seen[] = $current->id;

                continue;
            }

            $created = $task->subtasks()->create($attributes + [
                'project_id' => $task->project_id,
                'created_by_id' => $actor->id,
                'number' => $project?->nextTaskNumber(),
                'status' => $status,
                'completed_at' => $completed ? now() : null,
            ]);

            $seen[] = $created->id;

            // A sub-task is a task, and its creation belongs on its own trail.
            // This logs directly rather than dispatching TaskCreated: the event
            // also drives progress recalculation and automation, and firing
            // those per sub-task would change behaviour, not just record it.
            Activity::logFor('task.created', Activity::SUBJECT_TASK, $created->id, [
                'user_id' => $actor->id,
                'subject_user_id' => $created->assignee_id,
                'module' => 'tasks',
                'description' => "Created sub-task \"{$created->title}\" under {$task->key_label}",
                'properties' => ['project_id' => $created->project_id, 'parent_task_id' => $task->id],
            ]);

            if ($created->assignee_id) {
                $created->setRelation('project', $project);
                TaskAssigned::dispatch($created, $actor, $created->assignee_id, null);
            }
        }

        $removed = $existing->keys()->diff($seen);
        if ($removed->isNotEmpty()) {
            // Removing sub-tasks used to be a silent mass delete. Each one is
            // recorded against itself, so a deleted sub-task still has a trail.
            foreach ($existing->only($removed->all()) as $goner) {
                Activity::logFor('task.deleted', Activity::SUBJECT_TASK, $goner->id, [
                    'user_id' => $actor->id,
                    'module' => 'tasks',
                    'description' => "Removed sub-task \"{$goner->title}\" from {$task->key_label}",
                    'properties' => ['project_id' => $goner->project_id, 'parent_task_id' => $task->id],
                ]);
            }

            Task::query()->whereIn('id', $removed)->delete();
        }
    }

    private function doneKeyFor(?Project $project): string
    {
        if (! $project) {
            return 'completed';
        }

        $workflow = $this->workflows->forProject($project);

        return $workflow->doneStatuses()->first()?->key ?? 'completed';
    }

    // ------------------------------------------------------------------ labels / watchers

    /**
     * @param  array<int, int|string>|null  $labels  ids, or names for on-the-fly creation
     */
    private function syncLabels(Task $task, ?array $labels): void
    {
        if ($labels === null) {
            return;
        }

        $ids = [];

        foreach ($labels as $label) {
            if (is_numeric($label)) {
                $ids[] = (int) $label;

                continue;
            }

            $name = trim((string) $label);
            if ($name === '') {
                continue;
            }

            $ids[] = Label::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => 'slate'],
            )->id;
        }

        $task->labels()->sync(array_unique($ids));
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function addWatchers(Task $task, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return;
        }

        $task->watchers()->syncWithoutDetaching($userIds);
    }

    public function toggleWatch(Task $task, User $user): bool
    {
        if ($task->watchers()->whereKey($user->id)->exists()) {
            $task->watchers()->detach($user->id);

            return false;
        }

        $task->watchers()->attach($user->id);

        return true;
    }

    // ------------------------------------------------------------------ attachments

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

        Activity::logFor('task.attachment-added', Activity::SUBJECT_TASK, $task->id, [
            'module' => 'tasks',
            'description' => "Attached {$file->getClientOriginalName()} to {$task->key_label}",
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
        $attachmentId = $attachment->id;
        $attachment->deleteFile();
        $attachment->delete();

        Activity::logFor('task.attachment-removed', Activity::SUBJECT_TASK, $taskId, [
            'module' => 'tasks',
            'description' => "Removed attachment {$name} from task #{$taskId}",
            'properties' => ['task_id' => $taskId, 'attachment_id' => $attachmentId],
        ]);
    }

    // ------------------------------------------------------------------ time

    public function logTime(Task $task, User $user, array $data): TimeLog
    {
        return DB::transaction(function () use ($task, $user, $data) {
            $log = $task->timeLogs()->create([
                'user_id' => $user->id,
                'minutes' => (int) $data['minutes'],
                'started_at' => $data['started_at'],
                'note' => $data['note'] ?? null,
            ]);

            Activity::logFor('task.time-logged', Activity::SUBJECT_TASK, $task->id, [
                'subject_user_id' => $task->assignee_id,
                'module' => 'tasks',
                'description' => "Logged {$this->formatMinutes((int) $data['minutes'])} on {$task->key_label}",
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

            Activity::logFor('task.time-log-removed', Activity::SUBJECT_TASK, $task->id, [
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

    /**
     * Compact positions within a board column so ordering stays dense.
     * Uses a single CASE update rather than a query per row.
     */
    private function resequenceColumn(int $projectId, string $status): void
    {
        $ids = Task::query()
            ->where('project_id', $projectId)
            ->where('status', $status)
            ->whereNull('parent_task_id')
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $cases = [];
        $bindings = [];
        foreach ($ids as $i => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $i + 1;
        }

        // One statement instead of a query per row. Eloquent cannot bind inside
        // DB::raw, so this is issued directly with explicit bindings.
        DB::update(
            'UPDATE tasks SET position = CASE id '.implode(' ', $cases).' END WHERE id IN ('.$ids->map(fn () => '?')->implode(',').')',
            array_merge($bindings, $ids->all()),
        );
    }
}
