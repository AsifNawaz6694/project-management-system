<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Http\Requests\StatusChangeRequest;
use App\Modules\TaskManagement\Http\Requests\StoreTaskRequest;
use App\Modules\TaskManagement\Http\Requests\UpdateTaskRequest;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\TaskService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'project', 'priority', 'assignee', 'view']);

        $base = Task::query()
            ->root()
            ->visibleTo($user)
            ->with([
                'project:id,slug,title,color',
                'assignee:id,name,avatar',
                'creator:id,name,avatar',
            ])
            ->withCount(['subtasks', 'comments', 'attachments']);

        $base = $this->applyFilters($base, $filters);

        $stats = [
            'total' => (clone $base)->count(),
            'todo' => (clone $base)->where('status', 'todo')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        $tasks = $base->orderBy('position')->orderByDesc('id')->get();

        $projects = Project::visibleTo($user)
            ->orderBy('title')
            ->get(['id', 'slug', 'title', 'color']);

        $assignees = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title']),
                'initials' => $u->initials,
            ]);

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'stats' => $stats,
            'projects' => $projects,
            'assignees' => $assignees,
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('tasks/create', [
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color']),
            'assignees' => $this->assignableUsers(),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'preselect_project_id' => $request->integer('project_id') ?: null,
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
            ->with('status', 'Task created.');
    }

    public function show(Request $request, Task $task): Response
    {
        $user = $request->user();
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        $task->load([
            'project:id,slug,title,color',
            'assignee:id,name,avatar,job_title',
            'creator:id,name,avatar,job_title',
            'parent:id,title',
            'subtasks',
            'comments.user:id,name,avatar',
            'attachments.uploader:id,name,avatar',
            'timeLogs.user:id,name,avatar',
        ]);
        $task->append('logged_minutes');

        $activities = Activity::query()
            ->where('module', 'tasks')
            ->whereJsonContains('properties->task_id', $task->id)
            ->latest()
            ->limit(40)
            ->get()
            ->load('user:id,name,avatar');

        $comments = $task->comments;
        $rootComments = $comments
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
                        'created_at' => $r->created_at?->toISOString(),
                        'user' => $r->user,
                    ])->all();

                return [
                    'id' => $c->id,
                    'body' => $c->body,
                    'created_at' => $c->created_at?->toISOString(),
                    'user' => $c->user,
                    'replies' => $replies,
                ];
            })
            ->all();

        return Inertia::render('tasks/show', [
            'task' => $task,
            'activities' => $activities,
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color']),
            'assignees' => $this->assignableUsers(),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'comments' => $rootComments,
            'canEdit' => $user->hasPermission('tasks.update'),
            'canStatus' => $user->hasPermission('tasks.update') || ($task->assignee_id === $user->id && $user->hasPermission('tasks.update-status')),
            'canDelete' => $user->hasPermission('tasks.delete'),
            'canLogTime' => $user->hasPermission('tasks.log-time'),
        ]);
    }

    public function edit(Request $request, Task $task): Response
    {
        $user = $request->user();
        if (! $user->hasPermission('tasks.update')) {
            abort(403);
        }
        if (! Task::visibleTo($user)->whereKey($task->id)->exists()) {
            abort(403);
        }

        $task->load(['subtasks', 'project:id,slug,title']);

        return Inertia::render('tasks/edit', [
            'task' => [
                ...$task->toArray(),
                'subtasks' => $task->subtasks->map(fn ($s) => [
                    'title' => $s->title,
                    'description' => $s->description,
                    'due_date' => $s->due_date?->toDateString(),
                    'completed' => $s->completed_at !== null,
                    'assignee_id' => $s->assignee_id,
                ]),
            ],
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title', 'color']),
            'assignees' => $this->assignableUsers(),
            'statuses' => Task::STATUSES,
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
        $this->tasks->changeStatus($task, $request->string('status')->toString(), $request->integer('position') ?: null);

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        if (! $request->user()->hasPermission('tasks.delete')) {
            abort(403);
        }

        $this->tasks->delete($task);

        return redirect()
            ->route('tasks.index')
            ->with('status', 'Task deleted.');
    }

    private function applyFilters($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['project'] ?? null, fn ($q, $slug) => $q->whereHas('project', fn ($p) => $p->where('slug', $slug)))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['assignee'] ?? null, fn ($q, $id) => $id === 'me' ? $q->where('assignee_id', request()->user()->id) : $q->where('assignee_id', (int) $id));
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar', 'job_title', 'email'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title', 'email']),
                'initials' => $u->initials,
                'primary_role' => $u->primaryRole()?->slug,
            ]);
    }
}
