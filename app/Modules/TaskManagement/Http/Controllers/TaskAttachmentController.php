<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskAttachment;
use App\Modules\TaskManagement\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $user = $request->user();
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $this->tasks->attachFile($task, $request->file('file'), $user);

        return back()->with('status', 'Attachment uploaded.');
    }

    public function download(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        return $this->tasks->downloadAttachment($attachment);
    }

    public function destroy(Request $request, Task $task, TaskAttachment $attachment): RedirectResponse
    {
        $user = $request->user();
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }
        if ($attachment->uploader_id !== $user->id && ! $user->hasPermission('tasks.update')) {
            abort(403);
        }

        $this->tasks->deleteAttachment($attachment);

        return back()->with('status', 'Attachment removed.');
    }
}
