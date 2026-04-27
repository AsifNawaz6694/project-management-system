<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\TaskManagement\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->tasks->addComment($task, $user, $data['body']);

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

        $comment->delete();

        return back();
    }
}
