<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Bulk task operations.
 *
 * Every task in the set is authorised individually, the whole batch runs in one
 * transaction, and a single summarising activity row is written rather than one
 * per task — so bulk-editing fifty items does not flood the audit feed.
 */
class TaskBulkController extends Controller
{
    private const MAX_BATCH = 200;

    public function __construct(private readonly TaskService $tasks) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->authorize('bulkEdit', Task::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['status', 'assignee', 'team', 'priority', 'labels', 'archive', 'unarchive', 'delete'])],
            'status' => ['nullable', 'string', 'max:40'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['integer', 'exists:labels,id'],
        ]);

        $user = $request->user();
        $action = $data['action'];

        $tasks = Task::query()
            ->visibleTo($user)
            ->whereIn('id', $data['ids'])
            ->get();

        $ability = match ($action) {
            'status' => 'changeStatus',
            'archive', 'unarchive' => 'archive',
            'delete' => 'delete',
            default => 'update',
        };

        // Silently skipping unauthorised rows would be misleading, so count them.
        $permitted = $tasks->filter(fn (Task $t) => $user->can($ability, $t));
        $skipped = $tasks->count() - $permitted->count();
        $missing = count($data['ids']) - $tasks->count();

        if ($permitted->isEmpty()) {
            return back()->with('error', 'You do not have permission to change any of the selected tasks.');
        }

        $applied = DB::transaction(function () use ($permitted, $action, $data, $user) {
            $count = 0;

            foreach ($permitted as $task) {
                match ($action) {
                    'status' => $this->tasks->changeStatus($task, $data['status'], null, $user),
                    'assignee' => $this->tasks->update($task, ['assignee_id' => $data['assignee_id'] ?? null], $user),
                    'team' => $this->tasks->update($task, ['team_id' => $data['team_id'] ?? null], $user),
                    'priority' => $this->tasks->update($task, ['priority' => $data['priority']], $user),
                    'labels' => $this->tasks->update($task, ['labels' => $data['labels'] ?? []], $user),
                    'archive' => $this->tasks->archive($task, $user),
                    'unarchive' => $this->tasks->unarchive($task, $user),
                    'delete' => $this->tasks->delete($task),
                    default => null,
                };
                $count++;
            }

            return $count;
        });

        Activity::log('task.bulk-'.$action, [
            'module' => 'tasks',
            'description' => "Bulk {$action} applied to {$applied} task(s)",
            'properties' => [
                'action' => $action,
                'count' => $applied,
                'task_ids' => $permitted->pluck('id')->all(),
            ],
        ]);

        $message = "{$applied} task(s) updated.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (no permission).";
        }
        if ($missing > 0) {
            $message .= " {$missing} not found or not visible to you.";
        }

        return back()->with('status', $message);
    }
}
