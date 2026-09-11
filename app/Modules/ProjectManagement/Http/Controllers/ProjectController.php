<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\ProjectManagement\Http\Requests\StoreProjectRequest;
use App\Modules\ProjectManagement\Http\Requests\UpdateProjectRequest;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Services\ProjectService;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'priority']);

        $query = Project::query()
            ->visibleTo($user)
            ->with(['owner:id,name,avatar', 'members:id,name,avatar'])
            ->withCount(['milestones', 'members']);

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'planning' => (clone $query)->where('status', 'planning')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];

        $projects = $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            // Filters arrive as arrays from the multi-select controls; a single
            // value is still accepted so existing links keep working.
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereIn('status', (array) $s))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->whereIn('priority', (array) $p))
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'planning' THEN 2 WHEN 'on_hold' THEN 3 WHEN 'completed' THEN 4 WHEN 'cancelled' THEN 5 END")
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => $filters,
            'stats' => $stats,
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('projects/create', [
            'users' => $this->assignableUsers(),
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
            'colors' => Project::COLORS,
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projects->create($request->validated(), $request->user());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', "Project {$project->title} created.");
    }

    public function show(Request $request, Project $project): Response
    {
        // Opening the project clears its queued notifications for this user.
        app(NotificationService::class)
            ->markEntityRead($request->user(), 'project:'.$project->id);

        $user = $request->user();
        if (! Project::query()->visibleTo($user)->whereKey($project->id)->exists()) {
            abort(403);
        }

        $project->load([
            'owner:id,name,avatar,job_title',
            'members:id,name,avatar,job_title,department_id',
            'members.department:id,name',
            'milestones',
            'attachments.uploader:id,name,avatar',
            'comments.user:id,name,avatar',
        ]);

        // The project's own events plus everything that happened to its tasks.
        // The task ids stay a subquery so a large project never builds an IN
        // list in PHP just to read twenty rows.
        $activities = Activity::query()
            ->forProjectTimeline(
                $project->id,
                Task::query()->where('project_id', $project->id)->select('id'),
            )
            ->with('user:id,name,avatar')
            ->chronological()
            ->limit(20)
            ->get();

        $rootComments = $project->comments
            ->whereNull('parent_id')
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($comment) use ($project) {
                $replies = $project->comments
                    ->where('parent_id', $comment->id)
                    ->sortBy('created_at')
                    ->values();

                return [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'created_at' => $comment->created_at?->toISOString(),
                    'user' => $comment->user,
                    'replies' => $replies->map(fn ($r) => [
                        'id' => $r->id,
                        'body' => $r->body,
                        'created_at' => $r->created_at?->toISOString(),
                        'user' => $r->user,
                    ])->all(),
                ];
            });

        return Inertia::render('projects/show', [
            'project' => $project,
            'activities' => $activities,
            'milestoneStats' => [
                'total' => $project->milestones->count(),
                'completed' => $project->milestones->whereNotNull('completed_at')->count(),
            ],
            'comments' => $rootComments,
            'canUpload' => $request->user()->hasPermission('projects.update') || $project->owner_id === $request->user()->id,
        ]);
    }

    public function edit(Project $project): Response
    {
        $project->load(['members:id', 'milestones']);

        return Inertia::render('projects/edit', [
            'project' => [
                ...$project->toArray(),
                'member_ids' => $project->members->pluck('id')->all(),
            ],
            'users' => $this->assignableUsers(),
            'statuses' => Project::STATUSES,
            'priorities' => Project::PRIORITIES,
            'colors' => Project::COLORS,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projects->update($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        if (! $request->user()->hasPermission('projects.delete')) {
            abort(403);
        }

        $this->projects->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('status', 'Project deleted.');
    }

    private function assignableUsers()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', [Role::ADMIN, Role::MANAGER, Role::EMPLOYEE]))
            ->where('status', 'active')
            ->orderBy('name')
            ->with('department:id,name')
            ->get(['id', 'name', 'email', 'avatar', 'job_title', 'department_id'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'email', 'avatar', 'job_title']),
                'department' => $u->departmentName(),
                'initials' => $u->initials,
                'primary_role' => $u->primaryRole()?->slug,
            ]);
    }
}
