<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Reporting\Services\CumulativeFlowService;
use App\Modules\Reporting\Services\ExportService;
use App\Modules\TaskManagement\Services\TaskQueryService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exports,
        private readonly TaskQueryService $queries,
        private readonly CumulativeFlowService $flow,
    ) {}

    /**
     * Streams the current task selection as a spreadsheet-ready CSV.
     */
    public function tasks(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $this->queries->sanitise($request->all());

        Activity::log('reports.exported', [
            'module' => 'reports',
            'description' => 'Exported tasks to CSV',
            'properties' => ['filters' => $filters],
        ]);

        return $this->exports->streamTasks($user, $filters);
    }

    /**
     * A print-ready page. Browsers turn this into a PDF far better than a
     * server-side renderer would, and the output stays selectable.
     */
    public function printable(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->queries->sanitise($request->all());

        return Inertia::render('reports/printable', [
            'report' => $this->exports->printableTasks($user, $filters),
        ]);
    }

    /**
     * Cumulative flow: how work has piled up in each stage over time.
     */
    public function flow(Request $request): Response
    {
        $user = $request->user();
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [14, 30, 60, 90], true) ? $days : 30;

        $project = $request->filled('project')
            ? Project::query()->visibleTo($user)->where('slug', $request->query('project'))->first()
            : null;

        return Inertia::render('reports/flow', [
            'days' => $days,
            'project' => $project?->slug,
            'flow' => $this->flow->build($user, $days, $project),
            'projects' => Project::query()->visibleTo($user)->orderBy('title')->get(['slug', 'title'])
                ->map(fn (Project $p) => ['value' => $p->slug, 'label' => $p->title]),
        ]);
    }
}
