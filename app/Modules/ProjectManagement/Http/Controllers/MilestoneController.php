<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function toggle(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        $user = $request->user();

        if (! Project::query()->visibleTo($user)->whereKey($project->id)->exists()) {
            abort(403);
        }

        if (! $user->hasPermission('projects.update') && ! $user->hasPermission('tasks.update-status')) {
            abort(403);
        }

        if ($milestone->project_id !== $project->id) {
            abort(404);
        }

        $this->projects->toggleMilestone($milestone);

        return back();
    }
}
