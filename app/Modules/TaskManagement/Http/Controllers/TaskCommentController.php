<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Services\MentionParser;
use App\Modules\TaskManagement\Events\TaskCommented;
use App\Modules\TaskManagement\Models\CommentRevision;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\TaskManagement\Services\TaskService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    /** Authors may edit their own comment within this window. */
    private const EDIT_WINDOW_MINUTES = 60;

    public function __construct(private readonly TaskService $tasks) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
            'is_internal' => ['boolean'],
        ]);

        // A reply must belong to the same task, otherwise threads render in the
        // wrong place.
        if (! empty($data['parent_id'])) {
            $parentBelongs = TaskComment::query()
                ->whereKey($data['parent_id'])
                ->where('task_id', $task->id)
                ->exists();

            abort_unless($parentBelongs, 422, 'That comment does not belong to this task.');
        }

        $user = $request->user();
        $mentions = MentionParser::extract($data['body']);

        // An internal note is only offered to people who can edit the task;
        // anyone else silently posts an ordinary comment.
        $internal = (bool) ($data['is_internal'] ?? false) && $user->can('update', $task);

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'mentions' => $internal ? [] : $mentions,
            'is_internal' => $internal,
        ]);

        Activity::logFor('task.commented', Activity::SUBJECT_TASK, $task->id, [
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Commented on {$task->key_label}",
            'properties' => ['task_id' => $task->id, 'comment_id' => $comment->id],
        ]);

        if (! $internal && ! empty($mentions)) {
            MentionParser::notify(
                $mentions,
                "{$user->name} mentioned you on \"{$task->title}\"",
                ['task_id' => $task->id, 'comment_id' => $comment->id],
                $user->id,
            );
        }

        // Commenting makes you a follower of the conversation.
        $this->tasks->addWatchers($task, [$user->id]);

        TaskCommented::dispatch($task, $comment, $user, $mentions);

        return back();
    }

    public function update(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        $user = $request->user();

        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        abort_unless($comment->user_id === $user->id, 403, 'You can only edit your own comments.');

        abort_if(
            $comment->created_at?->diffInMinutes(now()) > self::EDIT_WINDOW_MINUTES && ! $user->isAdmin(),
            403,
            'This comment is too old to edit.',
        );

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // Keep what it said before, so "edited" is auditable.
        CommentRevision::query()->create([
            'task_comment_id' => $comment->id,
            'body' => $comment->body,
            'edited_by_id' => $user->id,
            'created_at' => now(),
        ]);

        $comment->update([
            'body' => $data['body'],
            'mentions' => $comment->is_internal ? [] : MentionParser::extract($data['body']),
            'edited_at' => now(),
        ]);

        Activity::logFor('task.comment-edited', Activity::SUBJECT_TASK, $task->id, [
            'module' => 'tasks',
            'description' => "Edited a comment on {$task->key_label}",
            'properties' => ['task_id' => $task->id, 'comment_id' => $comment->id],
        ]);

        return back()->with('status', 'Comment updated.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        $user = $request->user();

        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        if ($comment->user_id !== $user->id && ! $user->can('update', $task)) {
            abort(403);
        }

        $commentId = $comment->id;
        $comment->delete();

        Activity::logFor('task.comment-deleted', Activity::SUBJECT_TASK, $task->id, [
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Deleted a comment on {$task->key_label}",
            'properties' => ['task_id' => $task->id, 'comment_id' => $commentId],
        ]);

        return back();
    }
}
