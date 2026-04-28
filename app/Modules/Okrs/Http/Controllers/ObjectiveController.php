<?php

namespace App\Modules\Okrs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Okrs\Http\Requests\StoreObjectiveRequest;
use App\Modules\Okrs\Models\KeyResult;
use App\Modules\Okrs\Models\Objective;
use App\Modules\Okrs\Services\ObjectiveService;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Teams\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObjectiveController extends Controller
{
    public function __construct(private readonly ObjectiveService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->string('period')->toString() ?: null;

        $base = Objective::query()
            ->visibleTo($user)
            ->with(['owner:id,name,avatar', 'team:id,name,color', 'project:id,slug,title,color', 'keyResults:id,objective_id,title,start_value,target_value,current_value,status,owner_id,position'])
            ->orderByDesc('starts_at');

        if ($period) {
            $base->where('period', $period);
        }

        return Inertia::render('okrs/index', [
            'objectives' => $base->get()->map(fn (Objective $o) => [
                ...$o->toArray(),
                'key_results' => $o->keyResults->map(fn (KeyResult $k) => [
                    ...$k->toArray(),
                    'progress' => $k->progress,
                ]),
            ]),
            'periods' => Objective::query()->select('period')->distinct()->orderByDesc('period')->pluck('period'),
            'period' => $period,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('okrs/create', [
            'people' => $this->people(),
            'teams' => Team::orderBy('name')->get(['id', 'name', 'color']),
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title']),
            'parents' => Objective::visibleTo($user)->where('status', 'active')->orderBy('title')->get(['id', 'title', 'period']),
            'statuses' => Objective::STATUSES,
            'visibilities' => Objective::VISIBILITY,
            'metric_types' => KeyResult::METRIC_TYPES,
        ]);
    }

    public function store(StoreObjectiveRequest $request): RedirectResponse
    {
        $obj = $this->service->create($request->validated(), $request->user());

        return redirect()->route('okrs.show', $obj)->with('status', 'Objective created.');
    }

    public function show(Request $request, Objective $objective): Response
    {
        $user = $request->user();
        if (! Objective::visibleTo($user)->whereKey($objective->id)->exists()) {
            abort(403);
        }

        $objective->load([
            'owner:id,name,avatar,job_title',
            'team:id,name,color',
            'project:id,slug,title,color',
            'parent:id,title,period',
            'children:id,parent_id,title,progress,status,owner_id',
            'keyResults.owner:id,name,avatar',
            'keyResults.updates.recorder:id,name,avatar',
        ]);

        return Inertia::render('okrs/show', [
            'objective' => [
                ...$objective->toArray(),
                'key_results' => $objective->keyResults->map(fn (KeyResult $k) => [
                    ...$k->toArray(),
                    'progress' => $k->progress,
                    'updates' => $k->updates,
                ]),
            ],
            'canEdit' => $user->hasPermission('okrs.update') || $objective->owner_id === $user->id,
            'canDelete' => $user->hasPermission('okrs.delete') || $objective->owner_id === $user->id,
        ]);
    }

    public function edit(Request $request, Objective $objective): Response
    {
        $user = $request->user();
        if (! ($user->hasPermission('okrs.update') || $objective->owner_id === $user->id)) {
            abort(403);
        }

        $objective->load('keyResults');

        return Inertia::render('okrs/edit', [
            'objective' => $objective,
            'people' => $this->people(),
            'teams' => Team::orderBy('name')->get(['id', 'name', 'color']),
            'projects' => Project::visibleTo($user)->orderBy('title')->get(['id', 'slug', 'title']),
            'parents' => Objective::visibleTo($user)->where('id', '!=', $objective->id)->orderBy('title')->get(['id', 'title', 'period']),
            'statuses' => Objective::STATUSES,
            'visibilities' => Objective::VISIBILITY,
            'metric_types' => KeyResult::METRIC_TYPES,
        ]);
    }

    public function update(StoreObjectiveRequest $request, Objective $objective): RedirectResponse
    {
        $this->service->update($objective, $request->validated());

        return redirect()->route('okrs.show', $objective)->with('status', 'Objective updated.');
    }

    public function destroy(Request $request, Objective $objective): RedirectResponse
    {
        $user = $request->user();
        if (! ($user->hasPermission('okrs.delete') || $objective->owner_id === $user->id)) {
            abort(403);
        }
        $this->service->delete($objective);

        return redirect()->route('okrs.index')->with('status', 'Objective deleted.');
    }

    public function recordUpdate(Request $request, Objective $objective, KeyResult $keyResult): RedirectResponse
    {
        if ($keyResult->objective_id !== $objective->id) {
            abort(404);
        }

        $data = $request->validate([
            'value' => ['required', 'numeric'],
            'confidence' => ['required', 'in:on_track,at_risk,off_track,achieved'],
            'note' => ['nullable', 'string', 'max:1000'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $this->service->recordKrUpdate($keyResult, $data, $request->user());

        return back()->with('status', 'Progress recorded.');
    }

    private function people()
    {
        return User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'avatar', 'job_title'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title']),
                'initials' => $u->initials,
            ]);
    }
}
