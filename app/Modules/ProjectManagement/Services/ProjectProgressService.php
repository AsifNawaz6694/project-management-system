<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Services\WorkflowService;

/**
 * Derives a project's completion percentage.
 *
 * The audit found progress only ever moved when a milestone was toggled, so a
 * project that tracks work as tasks — which is most of them — sat at whatever
 * number someone typed on the day it was created.
 *
 * Precedence is deliberate: milestones are an explicit statement of what "done"
 * means, so they win where they exist. Tasks are the fallback. A project with
 * neither keeps its manually entered figure rather than being reset to zero.
 */
class ProjectProgressService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function recalculate(Project $project): int
    {
        $progress = $this->derive($project);

        if ($progress !== $project->progress) {
            // Quietly: progress is derived state, not something to audit or
            // notify about on its own.
            $project->forceFill(['progress' => $progress])->saveQuietly();
        }

        return $progress;
    }

    public function recalculateFor(?int $projectId): void
    {
        if ($projectId && $project = Project::query()->find($projectId)) {
            $this->recalculate($project);
        }
    }

    private function derive(Project $project): int
    {
        $milestones = Milestone::query()->where('project_id', $project->id)->count();

        if ($milestones > 0) {
            $completed = Milestone::query()
                ->where('project_id', $project->id)
                ->whereNotNull('completed_at')
                ->count();

            return (int) round($completed / $milestones * 100);
        }

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->root()
            ->notArchived();

        $total = (clone $tasks)->count();

        if ($total === 0) {
            return $project->progress;
        }

        $doneKeys = $this->workflows->doneStatusKeys();
        $done = (clone $tasks)->whereIn('status', $doneKeys ?: ['__none__'])->count();

        return (int) round($done / $total * 100);
    }
}
