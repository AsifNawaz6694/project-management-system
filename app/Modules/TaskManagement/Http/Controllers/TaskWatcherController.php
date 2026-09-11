<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskWatcherController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function toggle(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);

        $watching = $this->tasks->toggleWatch($task, $request->user());

        return back()->with(
            'status',
            $watching ? 'You are now watching this task.' : 'You stopped watching this task.',
        );
    }
}
