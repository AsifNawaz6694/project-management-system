<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Sprint;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\SprintService;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function __construct(
        private readonly SprintService $sprints,
        private readonly WorkflowService $workflows,
    ) {}

    /**
     * The backlog board: every sprint plus the unassigned pile beneath it.
     */
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $user = $request->user();

        $sprints = Sprint::query()
            ->where('project_id', $project->id)
            ->where('state', '!=', Sprint::STATE_COMPLETED)
            ->orderByRaw("FIELD(state, 'active', 'future')")
            ->orderBy('position')
            ->get();

        $tasks = Task::query()
            ->visibleTo($user)
            ->where('project_id', $project->id)
            ->root()
            ->notArchived()
            ->with(['assignee:id,name,avatar', 'labels:id,name,color'])
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        $doneKeys = $this->workflows->doneStatusKeys();
        $statuses = $this->workflows->statusesFor($project);

        return Inertia::render('sprints/backlog', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'key' => $project->key,
            ],
            'sprints' => $sprints->map(fn (Sprint $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'goal' => $s->goal,
                'state' => $s->state,
                'starts_at' => $s->starts_at?->toDateString(),
                'ends_at' => $s->ends_at?->toDateString(),
                'totals' => $this->sprints->totals($s),
            ]),
            'tasks' => $tasks->map(fn (Task $t) => [
                'id' => $t->id,
                'key' => $t->key_label,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'story_points' => $t->story_points,
                'sprint_id' => $t->sprint_id,
                'is_done' => in_array($t->status, $doneKeys, true),
                'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                'labels' => $t->labels->map(fn ($l) => ['name' => $l->name, 'color' => $l->color]),
            ]),
            'statuses' => $statuses,
            'can' => ['manage' => $user->can('update', $project)],
        ]);
    }

    /**
     * The sprint report: burndown for the running sprint, velocity across the
     * completed ones.
     */
    public function report(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $active = $this->sprints->currentFor($project);

        $completed = Sprint::query()
            ->where('project_id', $project->id)
            ->where('state', Sprint::STATE_COMPLETED)
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get();

        return Inertia::render('sprints/report', [
            'project' => ['id' => $project->id, 'slug' => $project->slug, 'title' => $project->title],
            'active' => $active ? [
                'id' => $active->id,
                'name' => $active->name,
                'goal' => $active->goal,
                'starts_at' => $active->starts_at?->toDateString(),
                'ends_at' => $active->ends_at?->toDateString(),
                'totals' => $this->sprints->totals($active),
                'burndown' => $this->sprints->burndown($active->load('snapshots')),
            ] : null,
            'velocity' => $this->sprints->velocity($project),
            'completed' => $completed->map(fn (Sprint $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'completed_at' => $s->completed_at?->toDateString(),
                'totals' => $this->sprints->totals($s),
            ]),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $this->sprints->create($project, $data);

        return back()->with('status', 'Sprint created.');
    }

    public function update(Request $request, Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->sprints->assertOwnedBy($sprint, $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $this->sprints->update($sprint, $data);

        return back()->with('status', 'Sprint updated.');
    }

    public function start(Request $request, Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->sprints->assertOwnedBy($sprint, $project);

        $this->sprints->start($sprint);

        return back()->with('status', "\"{$sprint->name}\" is running.");
    }

    public function complete(Request $request, Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->sprints->assertOwnedBy($sprint, $project);

        $data = $request->validate([
            'move_to' => ['required', Rule::in(['backlog', 'next'])],
            'target_sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        $this->sprints->complete($sprint, $data['move_to'], $data['target_sprint_id'] ?? null);

        return back()->with('status', "\"{$sprint->name}\" completed.");
    }

    public function destroy(Request $request, Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->sprints->assertOwnedBy($sprint, $project);

        $this->sprints->delete($sprint);

        return back()->with('status', 'Sprint deleted. Its work went back to the backlog.');
    }

    /**
     * Moves work between the backlog and a sprint.
     */
    public function assign(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['integer'],
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        $sprint = null;

        if (! empty($data['sprint_id'])) {
            $sprint = Sprint::query()->findOrFail($data['sprint_id']);
            $this->sprints->assertOwnedBy($sprint, $project);
        }

        $moved = $this->sprints->assign($sprint, $data['task_ids'], $project);

        return back()->with('status', $moved === 1 ? '1 item moved.' : "{$moved} items moved.");
    }

    public function estimate(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['integer'],
            'story_points' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);

        $this->sprints->setPoints($data['task_ids'], $data['story_points'] ?? null, $project);

        return back()->with('status', 'Estimate saved.');
    }
}
