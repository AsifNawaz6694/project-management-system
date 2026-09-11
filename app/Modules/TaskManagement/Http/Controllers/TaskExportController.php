<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskQueryService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the current task view.
 *
 * Streams in chunks so exporting a large workspace never materialises the whole
 * result set in memory. This is what the previously unimplemented
 * `reports.export` permission now actually gates.
 */
class TaskExportController extends Controller
{
    private const CHUNK = 500;

    private const COLUMNS = [
        'Key', 'Title', 'Type', 'Status', 'Priority', 'Project', 'Assignee', 'Team',
        'Reporter', 'Labels', 'Start date', 'Due date', 'Estimate (min)', 'Logged (min)',
        'Subtasks', 'Created at', 'Completed at',
    ];

    public function __construct(private readonly TaskQueryService $queries) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->hasPermission('reports.export'),
            403,
            'You do not have permission to export.',
        );

        $filters = $this->queries->sanitise($request->all());

        Activity::log('tasks.exported', [
            'module' => 'tasks',
            'description' => 'Exported the task list to CSV',
            'properties' => ['filters' => $filters],
        ]);

        $filename = 'tasks-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($user, $filters) {
            $handle = fopen('php://output', 'wb');

            // BOM so Excel opens UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::COLUMNS);

            $this->queries->build($user, $filters)
                ->with([
                    'project:id,key,title',
                    'assignee:id,name',
                    'team:id,name',
                    'creator:id,name',
                    'type:id,name',
                    'labels:id,name',
                ])
                ->withCount('subtasks')
                ->withSum('timeLogs as logged_total', 'minutes')
                ->reorder()
                ->orderBy('tasks.id')
                ->chunk(self::CHUNK, function ($tasks) use ($handle) {
                    foreach ($tasks as $task) {
                        fputcsv($handle, $this->row($task));
                    }
                    flush();
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return array<int, string|int|null>
     */
    private function row(Task $task): array
    {
        return [
            $task->key_label,
            $task->title,
            $task->type?->name,
            $task->status,
            $task->priority,
            $task->project?->title,
            $task->assignee?->name,
            $task->team?->name,
            $task->creator?->name,
            $task->labels->pluck('name')->implode(', '),
            $task->start_date?->toDateString(),
            $task->due_date?->toDateString(),
            $task->estimate_minutes,
            (int) ($task->logged_total ?? 0),
            (int) ($task->subtasks_count ?? 0),
            $task->created_at?->toDateTimeString(),
            $task->completed_at?->toDateTimeString(),
        ];
    }
}
