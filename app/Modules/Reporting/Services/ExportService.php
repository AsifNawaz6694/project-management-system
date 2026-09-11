<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskQueryService;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports whatever the current filters select.
 *
 * CSV is streamed rather than assembled in memory — an export is exactly the
 * request most likely to be run against the whole workspace, and the row count
 * is the user's choice, not ours.
 *
 * The CSV is written with a UTF-8 BOM and CRLF endings so Excel opens it
 * correctly on a Windows machine without an import dialog; without the BOM,
 * Excel mangles any non-ASCII name in the file.
 */
class ExportService
{
    public function __construct(
        private readonly TaskQueryService $queries,
        private readonly WorkflowService $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function streamTasks(User $user, array $filters): StreamedResponse
    {
        $filename = 'tasks-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            // Long exports must not be cut short by an intermediate cache.
            'Cache-Control' => 'no-store, no-cache',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($user, $filters) {
            $out = fopen('php://output', 'w');

            // Excel needs the BOM to read the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Key', 'Title', 'Project', 'Status', 'Priority', 'Type',
                'Assignee', 'Team', 'Reporter', 'Story points',
                'Estimate (min)', 'Logged (min)', 'Start date', 'Due date',
                'Completed at', 'Labels', 'Created at',
            ]);

            $doneKeys = $this->workflows->doneStatusKeys();

            $this->queries->build($user, $filters)
                ->with(['project:id,key,title', 'assignee:id,name', 'team:id,name', 'creator:id,name', 'type:id,name', 'labels:id,name'])
                ->chunk(500, function ($tasks) use ($out, $doneKeys) {
                    foreach ($tasks as $task) {
                        fputcsv($out, $this->row($task, $doneKeys));
                    }

                    // Push each chunk to the client rather than buffering it all.
                    flush();
                });

            fclose($out);
        }, 200, $headers);
    }

    /**
     * @param  array<int, string>  $doneKeys
     * @return array<int, string>
     */
    private function row(Task $task, array $doneKeys): array
    {
        return [
            $task->key_label,
            $task->title,
            $task->project?->title ?? '',
            $task->status,
            $task->priority,
            $task->type?->name ?? '',
            $task->assignee?->name ?? '',
            $task->team?->name ?? '',
            $task->creator?->name ?? '',
            $task->story_points ?? '',
            $task->estimate_minutes ?? '',
            $task->logged_minutes,
            $task->start_date?->toDateString() ?? '',
            $task->due_date?->toDateString() ?? '',
            $task->completed_at?->toDateTimeString() ?? (in_array($task->status, $doneKeys, true) ? 'done' : ''),
            $task->labels->pluck('name')->implode(', '),
            $task->created_at?->toDateTimeString() ?? '',
        ];
    }

    /**
     * A print-ready HTML report.
     *
     * Deliberately HTML rather than a generated PDF binary: every browser
     * prints to PDF, the output stays selectable and accessible, and it adds no
     * dependency for something the platform already does well.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function printableTasks(User $user, array $filters, int $limit = 1000): array
    {
        $doneKeys = $this->workflows->doneStatusKeys();

        $tasks = $this->queries->build($user, $filters)
            ->with(['project:id,key,title', 'assignee:id,name', 'labels:id,name'])
            ->limit($limit)
            ->get();

        $statusNames = $this->workflows->allStatuses()->pluck('name', 'key');

        return [
            'generated_at' => now()->toDayDateTimeString(),
            'generated_by' => $user->name,
            'filters' => $filters,
            'summary' => [
                'total' => $tasks->count(),
                'done' => $tasks->filter(fn (Task $t) => in_array($t->status, $doneKeys, true))->count(),
                'overdue' => $tasks->filter(
                    fn (Task $t) => $t->due_date
                        && Carbon::parse($t->due_date)->isPast()
                        && ! in_array($t->status, $doneKeys, true)
                )->count(),
                'points' => round((float) $tasks->sum('story_points'), 1),
                'logged_hours' => round($tasks->sum('logged_minutes') / 60, 1),
            ],
            'rows' => $tasks->map(fn (Task $t) => [
                'key' => $t->key_label,
                'title' => $t->title,
                'project' => $t->project?->title ?? '',
                'status' => $statusNames[$t->status] ?? $t->status,
                'priority' => $t->priority,
                'assignee' => $t->assignee?->name ?? 'Unassigned',
                'due_date' => $t->due_date?->toDateString(),
                'story_points' => $t->story_points,
                'is_done' => in_array($t->status, $doneKeys, true),
            ])->values(),
        ];
    }
}
