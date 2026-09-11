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
        $this->authorize('attach', $task);

        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            // Block executable and script uploads outright.
            'file.*' => ['nullable'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());
        abort_if(
            in_array($extension, ['php', 'phtml', 'exe', 'sh', 'bat', 'cmd', 'com', 'js', 'jar', 'msi'], true),
            422,
            'That file type is not allowed.',
        );

        $this->tasks->attachFile($task, $request->file('file'), $user);

        return back()->with('status', 'Attachment uploaded.');
    }

    public function download(Request $request, Task $task, TaskAttachment $attachment): StreamedResponse
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        $this->authorize('view', $task);

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
