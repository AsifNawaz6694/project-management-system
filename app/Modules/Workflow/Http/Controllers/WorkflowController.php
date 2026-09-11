<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use App\Modules\Workflow\Http\Requests\StoreStatusRequest;
use App\Modules\Workflow\Http\Requests\StoreWorkflowRequest;
use App\Modules\Workflow\Http\Requests\UpdateTransitionsRequest;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowAssignment;
use App\Modules\Workflow\Models\WorkflowStatus;
use App\Modules\Workflow\Services\WorkflowEditorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public const COLORS = ['slate', 'blue', 'sky', 'violet', 'emerald', 'amber', 'rose', 'pink'];

    public function __construct(private readonly WorkflowEditorService $editor) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $workflows = Workflow::query()
            ->with('statuses')
            ->withCount(['projects', 'transitions', 'assignments'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('workflows/index', [
            'workflows' => $workflows->map(fn (Workflow $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
                'is_default' => $w->is_default,
                'is_system' => $w->is_system,
                'projects_count' => $w->projects_count,
                'transitions_count' => $w->transitions_count,
                'assignments_count' => $w->assignments_count,
                'statuses' => $w->statuses->map(fn (WorkflowStatus $s) => [
                    'key' => $s->key,
                    'name' => $s->name,
                    'color' => $s->color,
                    'category' => $s->category,
                ]),
            ]),
            'can' => ['manage' => $user->isAdmin() || $user->hasPermission('workflows.manage')],
        ]);
    }

    public function store(StoreWorkflowRequest $request): RedirectResponse
    {
        $workflow = $this->editor->create($request->validated());

        return redirect()
            ->route('workflows.edit', $workflow)
            ->with('status', "Workflow \"{$workflow->name}\" created. Add your statuses and rules.");
    }

    public function edit(Workflow $workflow): Response
    {
        $workflow->load(['statuses', 'transitions']);

        $byId = $workflow->statuses->keyBy('id');

        // Task counts per status, so the UI can explain why a status cannot be
        // deleted before the user tries.
        $projectIds = Project::query()->where('workflow_id', $workflow->id)->pluck('id');

        $usage = Task::query()
            ->whereIn('project_id', $projectIds)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        return Inertia::render('workflows/edit', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_default' => $workflow->is_default,
                'is_system' => $workflow->is_system,
            ],
            'statuses' => $workflow->statuses->map(fn (WorkflowStatus $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'name' => $s->name,
                'category' => $s->category,
                'color' => $s->color,
                'position' => $s->position,
                'is_initial' => $s->is_initial,
                'task_count' => (int) ($usage[$s->key] ?? 0),
            ]),
            // Flattened to status keys — the shape the editor works in.
            'transitions' => $workflow->transitions->map(fn ($t) => [
                'from' => $t->from_status_id ? $byId[$t->from_status_id]?->key : null,
                'to' => $byId[$t->to_status_id]?->key,
                'requires_comment' => (bool) $t->requires_comment,
                'comment_label' => $t->comment_label,
                'required_permission' => $t->required_permission,
            ])->filter(fn ($t) => $t['to'] !== null)->values(),
            'projects' => Project::query()
                ->orderBy('title')
                ->get(['id', 'key', 'title', 'workflow_id'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'key' => $p->key,
                    'title' => $p->title,
                    'assigned' => $p->workflow_id === $workflow->id,
                ]),
            'people' => $this->assignablePeople($workflow),
            'teams' => $this->assignableTeams($workflow),
            'categories' => WorkflowStatus::CATEGORIES,
            'colors' => self::COLORS,
        ]);
    }

    public function update(StoreWorkflowRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->editor->update($workflow, $request->validated());

        return back()->with('status', 'Workflow updated.');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        $this->editor->delete($workflow);

        return redirect()->route('workflows.index')->with('status', 'Workflow deleted.');
    }

    // ------------------------------------------------------------------ statuses

    public function storeStatus(StoreStatusRequest $request, Workflow $workflow): RedirectResponse
    {
        $status = $this->editor->addStatus($workflow, $request->validated());

        return back()->with('status', "Status \"{$status->name}\" added.");
    }

    public function updateStatus(StoreStatusRequest $request, Workflow $workflow, WorkflowStatus $status): RedirectResponse
    {
        abort_unless($status->workflow_id === $workflow->id, 404);

        $this->editor->updateStatus($status, $request->validated());

        return back()->with('status', 'Status updated.');
    }

    public function destroyStatus(Workflow $workflow, WorkflowStatus $status): RedirectResponse
    {
        abort_unless($status->workflow_id === $workflow->id, 404);

        $this->editor->deleteStatus($status);

        return back()->with('status', 'Status removed.');
    }

    public function reorderStatuses(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->editor->reorderStatuses($workflow, $data['ids']);

        return back();
    }

    // ------------------------------------------------------------------ transitions

    public function updateTransitions(UpdateTransitionsRequest $request, Workflow $workflow): RedirectResponse
    {
        $this->editor->replaceTransitions($workflow, $request->validated()['transitions'] ?? []);

        return back()->with('status', 'Transition rules saved.');
    }

    // ------------------------------------------------------------------ assignment

    public function assignProjects(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'project_ids' => ['array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $count = $this->editor->assignProjects($workflow, $data['project_ids'] ?? []);

        return back()->with('status', "{$count} project(s) now use this workflow.");
    }

    public function assignPeople(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $count = $this->editor->assignPeople($workflow, $data['user_ids'] ?? []);

        return back()->with('status', "{$count} person(s) now follow this chain.");
    }

    public function assignTeams(Request $request, Workflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'team_ids' => ['array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        $count = $this->editor->assignTeams($workflow, $data['team_ids'] ?? []);

        return back()->with('status', "{$count} team(s) now follow this chain.");
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Everyone, flagged with the chain they follow — so the editor can show at
     * a glance that ticking someone takes them off another chain rather than
     * silently reassigning them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignablePeople(Workflow $workflow): array
    {
        $held = $this->assignmentMap(WorkflowAssignment::TYPE_USER);

        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'job_title'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'job_title' => $u->job_title,
                'assigned' => ($held[$u->id]['id'] ?? null) === $workflow->id,
                'other_chain' => isset($held[$u->id]) && $held[$u->id]['id'] !== $workflow->id
                    ? $held[$u->id]['name']
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assignableTeams(Workflow $workflow): array
    {
        $held = $this->assignmentMap(WorkflowAssignment::TYPE_TEAM);

        return Team::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'color'])
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'color' => $t->color,
                'assigned' => ($held[$t->id]['id'] ?? null) === $workflow->id,
                'other_chain' => isset($held[$t->id]) && $held[$t->id]['id'] !== $workflow->id
                    ? $held[$t->id]['name']
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * subject id => { id, name } of the chain it currently follows.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function assignmentMap(string $type): array
    {
        return WorkflowAssignment::query()
            ->where('assignable_type', $type)
            ->with('workflow:id,name')
            ->get()
            ->mapWithKeys(fn (WorkflowAssignment $a) => [
                $a->assignable_id => ['id' => $a->workflow_id, 'name' => $a->workflow?->name ?? ''],
            ])
            ->all();
    }
}
