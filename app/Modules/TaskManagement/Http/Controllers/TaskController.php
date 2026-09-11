<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Http\Requests\StatusChangeRequest;
use App\Modules\TaskManagement\Http\Requests\StoreTaskRequest;
use App\Modules\TaskManagement\Http\Requests\UpdateTaskRequest;
use App\Modules\TaskManagement\Models\Label;
use App\Modules\TaskManagement\Models\SavedFilter;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskLink;
use App\Modules\TaskManagement\Models\TaskType;
use App\Modules\TaskManagement\Services\SubtaskRollupService;
use App\Modules\TaskManagement\Services\TaskLinkService;
use App\Modules\TaskManagement\Services\TaskQueryService;
use App\Modules\TaskManagement\Services\TaskService;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskQueryService $queries,
        private readonly TaskLinkService $links,
        private readonly WorkflowService $workflows,
        private readonly NotificationService $notifications,
        private readonly SubtaskRollupService $rollup,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();
        $filters = $this->queries->sanitise($request->all());
        $view = $filters['view'] ?? 'kanban';

        $query = $this->queries->build($user, $filters)->with($this->queries->listRelations())
            ->withCount(['subtasks', 'comments', 'attachments']);

        // The board needs every card to render its columns; the list paginates.
        if ($view === 'kanban') {
            $tasks = $query->limit(500)->get();
            $paginator = null;
        } else {
            $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
            $tasks = collect($paginator->items());
        }

        return Inertia::render('tasks/index', [
            'tasks' => $tasks->map(fn (Task $t) => $this->cardPayload($t))->values(),
            'pagination' => $paginator ? [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ] : null,
            'filters' => $filters,
            'stats' => $this->queries->statusCounts($user, $filters),
            'statuses' => $this->workflowStatuses($user, $filters),
            // Which statuses each project actually permits. The merged board can
            // render a column a given task's project does not allow, so the
            // client needs this to avoid offering a move the server will reject.
            'projectStatuses' => $this->projectStatusMap($tasks),
            // "from>to" => prompt, for the moves that demand a written reason.
            'reasonRules' => $this->reasonRuleMap($tasks),
            'projects' => $this->visibleProjects($user),
            'assignees' => $this->assignableUsers(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'types' => TaskType::query()->orderBy('position')->get(['id', 'key', 'name', 'icon', 'color']),
            'priorities' => Task::PRIORITIES,
            'savedFilters' => $this->savedFiltersFor($user),
            'sortable' => TaskQueryService::SORTABLE,
            'can' => [
                'create' => $user->can('create', Task::class),
                'bulkEdit' => $user->can('bulkEdit', Task::class),
                'export' => $user->hasPermission('reports.export') || $user->isAdmin(),
                // Lifecycle actions are a manager's, so the bulk bar and the
                // trash link are hidden rather than offered and then refused.
                'lifecycle' => $user->can('viewTrash', Task::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Task::class);

        $user = $request->user();
        $projects = $this->visibleProjects($user);
        $preselect = $request->integer('project_id') ?: null;

        $project = $preselect
            ? Project::query()->find($preselect)
            : Project::query()->whereIn('id', $projects->pluck('id'))->first();

        return Inertia::render('tasks/create', [
            'projects' => $projects,
            'assignees' => $this->assignableUsers(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'types' => TaskType::query()->forProject($project)->where('is_subtask_type', false)->orderBy('position')
                ->get(['id', 'key', 'name', 'icon', 'color']),
            'statuses' => $project ? $this->workflows->statusesFor($project) : [],
            'priorities' => Task::PRIORITIES,
            'preselect_project_id' => $preselect,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create(
            $request->validated(),
            $request->user(),
            $request->file('attachments') ?? [],
        );

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', "Task {$task->key_label} created.");
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $user = $request->user();

        // Opening the task counts as reading everything queued about it.
        $this->notifications->markEntityRead($user, 'task:'.$task->id);

        $task->load([
            'project:id,slug,key,title,color,workflow_id',
            'type:id,key,name,icon,color',
            'assignee:id,name,avatar,job_title',
            'creator:id,name,avatar,job_title',
            'team:id,name,slug,color',
            'parent:id,number,project_id,title',
            'subtasks.assignee:id,name,avatar',
            'labels:id,name,slug,color',
            'comments.user:id,name,avatar',
            'attachments.uploader:id,name,avatar',
            'timeLogs.user:id,name,avatar',
            'watchers:id,name,avatar',
        ]);
        $task->append(['logged_minutes', 'remaining_minutes', 'key_label']);

        $doneKeys = $this->workflows->doneStatusKeys();

        return Inertia::render('tasks/show', [
            'task' => $task,
            'links' => $this->links->forTask($task, $doneKeys),
            'linkTypes' => collect(TaskLink::SELECTABLE)
                ->map(fn ($t) => ['value' => $t, 'label' => TaskLink::LABELS[$t]])->all(),
            'isBlocked' => $task->isBlocked($doneKeys),
            'isWatching' => $task->watchers->contains('id', $user->id),
            'statusHistory' => $task->statusHistory()->with('user:id,name,avatar')->limit(30)->get(),
            'activities' => $this->taskActivity($task),
            'projects' => $this->visibleProjects($user),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'types' => TaskType::query()->forProject($task->project)->orderBy('position')->get(['id', 'key', 'name', 'icon', 'color']),
            'statuses' => $this->workflows->statusesFor($task->project),
            'allowedTransitions' => $this->workflows->availableTransitions($task, $user),
            // Each legal move, flagged with whether it needs a written reason.
            'transitionOptions' => $this->workflows->transitionOptions($task, $user),
            'priorities' => Task::PRIORITIES,
            // Completion across the whole sub-tree, not just direct children.
            'subtaskProgress' => $this->rollup->progressOf($task),
            'comments' => $this->commentTree($task, $user->can('update', $task)),
            'can' => [
                'edit' => $user->can('update', $task),
                'status' => $user->can('changeStatus', $task),
                'delete' => $user->can('delete', $task),
                'archive' => $user->can('archive', $task),
                'logTime' => $user->can('logTime', $task),
                'link' => $user->can('link', $task),
                'comment' => $user->can('comment', $task),
                'attach' => $user->can('attach', $task),
            ],
        ]);
    }

    public function edit(Request $request, Task $task): Response
    {
        $this->authorize('update', $task);

        $user = $request->user();
        $task->load(['subtasks', 'labels:id,name,slug', 'project:id,slug,key,title,workflow_id']);

        return Inertia::render('tasks/edit', [
            'task' => [
                ...$task->toArray(),
                'label_ids' => $task->labels->pluck('id'),
                // Subtasks now carry their id so the server can reconcile rather
                // than delete-and-recreate on every save.
                'subtasks' => $task->subtasks->map(fn (Task $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'description' => $s->description,
                    'due_date' => $s->due_date?->toDateString(),
                    'completed' => $s->completed_at !== null,
                    'assignee_id' => $s->assignee_id,
                ]),
            ],
            'projects' => $this->visibleProjects($user),
            'assignees' => $this->assignableUsers(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'types' => TaskType::query()->forProject($task->project)->orderBy('position')->get(['id', 'key', 'name', 'icon', 'color']),
            'statuses' => $this->workflows->statusesFor($task->project),
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $request->validated(), $request->user());

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', 'Task updated.');
    }

    public function changeStatus(StatusChangeRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->changeStatus(
            $task,
            $request->string('status')->toString(),
            $request->has('position') ? $request->integer('position') : null,
            $request->user(),
            $request->input('reason'),
        );

        return back();
    }

    public function archive(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('archive', $task);
        $this->tasks->archive($task, $request->user());

        return back()->with('status', "{$task->key_label} archived.");
    }

    public function unarchive(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('archive', $task);
        $this->tasks->unarchive($task, $request->user());

        return back()->with('status', "{$task->key_label} restored from archive.");
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $this->tasks->delete($task);

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task deleted. It can be restored from the trash.');
    }

    /**
     * Soft-deleted tasks — previously unreachable through the application.
     */
    public function trash(Request $request): Response
    {
        $this->authorize('viewTrash', Task::class);

        $user = $request->user();

        $tasks = Task::onlyTrashed()
            ->visibleTo($user)
            ->with(['project:id,slug,key,title,color', 'assignee:id,name,avatar'])
            ->orderByDesc('deleted_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('tasks/trash', [
            'tasks' => collect($tasks->items())->map(fn (Task $t) => [
                'id' => $t->id,
                'key' => $t->key_label,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'project' => $t->project,
                'assignee' => $t->assignee,
                'deleted_at' => $t->deleted_at?->toISOString(),
            ])->values(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
            'can' => ['restore' => $user->can('viewTrash', Task::class)],
        ]);
    }

    public function restore(Request $request, int $task): RedirectResponse
    {
        $model = Task::onlyTrashed()->findOrFail($task);
        $this->authorize('delete', $model);

        $this->tasks->restore($model);

        return back()->with('status', "{$model->key_label} restored.");
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'key' => $task->key_label,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'position' => $task->position,
            'due_date' => $task->due_date?->toDateString(),
            'start_date' => $task->start_date?->toDateString(),
            'completed_at' => $task->completed_at?->toISOString(),
            'archived_at' => $task->archived_at?->toISOString(),
            'estimate_minutes' => $task->estimate_minutes,
            'project' => $task->project,
            'assignee' => $task->assignee,
            'team' => $task->team,
            'type' => $task->type,
            'labels' => $task->labels,
            'subtasks_count' => $task->subtasks_count ?? 0,
            'comments_count' => $task->comments_count ?? 0,
            'attachments_count' => $task->attachments_count ?? 0,
        ];
    }

    /**
     * Board columns. When a single project is in scope its own workflow drives
     * the columns; otherwise every distinct status across visible workflows is
     * merged so no task is left without a home column.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function workflowStatuses(User $user, array $filters): array
    {
        if (! empty($filters['project'])) {
            $project = Project::query()->where('slug', $filters['project'])->first();

            if ($project) {
                return $this->workflows->statusesFor($project);
            }
        }

        return $this->workflows->allStatuses()
            ->unique('key')
            ->sortBy('position')
            ->map(fn ($s) => [
                'key' => $s->key,
                'name' => $s->name,
                'category' => $s->category,
                'color' => $s->color,
                'position' => $s->position,
                'is_initial' => $s->is_initial,
            ])->values()->all();
    }

    /**
     * project id => { "from>to": prompt } for transitions that require a reason.
     *
     * Only the transitions that actually demand one are sent, so this stays
     * small even for a ten-status pipeline.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<string, string>>
     */
    private function reasonRuleMap($tasks): array
    {
        $projects = $tasks->pluck('project')->filter()->unique('id');

        $map = [];

        foreach ($projects as $project) {
            $workflow = $this->workflows->forProject($project);
            $rules = [];

            foreach ($workflow->transitions as $transition) {
                if (! $transition->requires_comment) {
                    continue;
                }

                $toKey = $workflow->statuses->firstWhere('id', $transition->to_status_id)?->key;

                if (! $toKey) {
                    continue;
                }

                $label = $transition->comment_label ?: 'Add a reason for this change.';

                if ($transition->from_status_id === null) {
                    // Wildcard: applies from every status that has no explicit rule.
                    $rules['*>'.$toKey] = $label;

                    continue;
                }

                $fromKey = $workflow->statuses->firstWhere('id', $transition->from_status_id)?->key;

                if ($fromKey) {
                    $rules[$fromKey.'>'.$toKey] = $label;
                }
            }

            $map[$project->id] = $rules;
        }

        return $map;
    }

    /**
     * project id => allowed status keys, for the projects present in this page.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<int, string>>
     */
    private function projectStatusMap($tasks): array
    {
        $projects = $tasks->pluck('project')->filter()->unique('id');

        $map = [];
        foreach ($projects as $project) {
            $map[$project->id] = $this->workflows->statusKeysFor($project);
        }

        return $map;
    }

    private function visibleProjects(User $user)
    {
        return Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'key', 'title', 'color', 'workflow_id']);
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title']),
                'initials' => $u->initials,
            ]);
    }

    private function savedFiltersFor(User $user)
    {
        return SavedFilter::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('is_shared', true))
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'query', 'is_shared'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'query' => $f->query,
                'is_shared' => $f->is_shared,
                'is_owner' => $f->user_id === $user->id,
            ]);
    }

    private function taskActivity(Task $task)
    {
        return Activity::query()
            ->forSubject(Activity::SUBJECT_TASK, $task->id)
            ->with('user:id,name,avatar')
            ->chronological()
            ->limit(40)
            ->get();
    }

    /**
     * Two-level comment tree, assembled from the already-loaded collection.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * The thread as the caller is allowed to see it.
     *
     * Internal notes are filtered out server-side rather than hidden in the UI:
     * a payload the client is trusted to conceal is not a permission.
     */
    private function commentTree(Task $task, bool $seesInternal = false): array
    {
        $comments = $seesInternal
            ? $task->comments
            : $task->comments->where('is_internal', false);

        return $comments
            ->whereNull('parent_id')
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($c) use ($comments) {
                $replies = $comments
                    ->where('parent_id', $c->id)
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'body' => $r->body,
                        'is_internal' => (bool) $r->is_internal,
                        'created_at' => $r->created_at?->toISOString(),
                        'edited_at' => $r->edited_at?->toISOString(),
                        'user' => $r->user,
                    ])->all();

                return [
                    'id' => $c->id,
                    'body' => $c->body,
                    'is_internal' => (bool) $c->is_internal,
                    'created_at' => $c->created_at?->toISOString(),
                    'edited_at' => $c->edited_at?->toISOString(),
                    'user' => $c->user,
                    'replies' => $replies,
                ];
            })
            ->all();
    }
}
