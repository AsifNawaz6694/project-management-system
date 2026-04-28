<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Services\MentionParser;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:task_comments,id'],
        ]);

        $mentions = MentionParser::extract($data['body']);

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'mentions' => $mentions,
        ]);

        Activity::log('task.commented', [
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Commented on {$task->title}",
            'properties' => ['task_id' => $task->id, 'comment_id' => $comment->id],
        ]);

        if (! empty($mentions)) {
            MentionParser::notify(
                $mentions,
                "{$user->name} mentioned you on \"{$task->title}\"",
                ['task_id' => $task->id, 'comment_id' => $comment->id],
                $user->id,
            );
        }

        return back();
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        $user = $request->user();
        if ($comment->task_id !== $task->id) {
            abort(404);
        }
        if ($comment->user_id !== $user->id && ! $user->hasPermission('tasks.update')) {
            abort(403);
        }

        $commentId = $comment->id;
        $comment->delete();

        Activity::log('task.comment-deleted', [
            'subject_user_id' => $task->assignee_id,
            'module' => 'tasks',
            'description' => "Deleted a comment on task \"{$task->title}\"",
            'properties' => ['task_id' => $task->id, 'comment_id' => $commentId],
        ]);

        return back();
    }
}
