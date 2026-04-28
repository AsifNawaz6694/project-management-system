<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Http\Requests\StoreTimeLogRequest;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TimeLog;
use App\Modules\TaskManagement\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeLogController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function store(StoreTimeLogRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->logTime($task, $request->user(), $request->validated());

        return back()->with('status', 'Time logged.');
    }

    public function destroy(Request $request, Task $task, TimeLog $timeLog): RedirectResponse
    {
        if ($timeLog->task_id !== $task->id) {
            abort(404);
        }

        $user = $request->user();
        if ($timeLog->user_id !== $user->id && ! $user->hasPermission('tasks.update')) {
            abort(403);
        }

        $this->tasks->deleteTimeLog($timeLog);

        return back()->with('status', 'Time log removed.');
    }
}
