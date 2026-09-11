<?php

namespace App\Modules\Teams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Teams\Http\Requests\StoreTeamRequest;
use App\Modules\Teams\Http\Requests\UpdateTeamRequest;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->with(['lead:id,name,avatar,job_title', 'members:id,name,avatar'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        return Inertia::render('teams/index', [
            'teams' => $teams,
            'canManage' => $request->user()->hasPermission('teams.manage'),
            'users' => $this->assignableUsers(),
            'colors' => Team::COLORS,
            'stats' => [
                'total' => $teams->count(),
                'with_lead' => $teams->whereNotNull('lead_id')->count(),
                'members_total' => $teams->sum('members_count'),
            ],
        ]);
    }

    public function show(Request $request, Team $team): Response
    {
        $team->load(['lead:id,name,avatar,job_title', 'members:id,name,avatar,job_title,department_id,email', 'members.department:id,name']);

        $activities = Activity::query()
            ->forSubject(Activity::SUBJECT_TEAM, $team->id)
            ->chronological()
            ->limit(40)
            ->get()
            ->load('user:id,name,avatar');

        return Inertia::render('teams/show', [
            'team' => $team,
            'activities' => $activities,
            'canManage' => $request->user()->hasPermission('teams.manage'),
            'users' => $this->assignableUsers(),
            'colors' => Team::COLORS,
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->only(['name', 'description', 'color', 'lead_id']));

        $memberIds = collect($request->input('member_ids', []))
            ->push($request->input('lead_id'))
            ->filter()->unique()->values()->all();

        $sync = [];
        foreach ($memberIds as $id) {
            $sync[(int) $id] = ['role' => $id == $request->input('lead_id') ? 'lead' : 'member'];
        }
        $team->members()->sync($sync);

        Activity::logFor('team.created', Activity::SUBJECT_TEAM, $team->id, [
            'module' => 'teams',
            'description' => "Created team {$team->name} with ".count($memberIds).' member(s)',
            'properties' => ['team' => $team->slug, 'members' => $memberIds],
        ]);

        return redirect()->route('teams.show', $team)->with('status', "Team {$team->name} created.");
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $before = $team->only(['name', 'description', 'color', 'lead_id']);
        $membersBefore = $team->members()->pluck('users.id')->all();

        $team->fill($request->only(['name', 'description', 'color', 'lead_id']))->save();

        $membersAfter = $membersBefore;

        if ($request->has('member_ids')) {
            $memberIds = collect($request->input('member_ids', []))
                ->push($team->lead_id)
                ->filter()->unique()->values()->all();
            $sync = [];
            foreach ($memberIds as $id) {
                $sync[(int) $id] = ['role' => $id == $team->lead_id ? 'lead' : 'member'];
            }
            $team->members()->sync($sync);
            $membersAfter = array_map('intval', $memberIds);
        }

        // Structured before/after, the same shape tasks and projects record,
        // rather than prose that cannot be queried.
        $changes = [];
        foreach ($before as $field => $was) {
            if ((string) $was !== (string) $team->getAttribute($field)) {
                $changes[$field] = [$was, $team->getAttribute($field)];
            }
        }

        $added = array_values(array_diff($membersAfter, $membersBefore));
        $removed = array_values(array_diff($membersBefore, $membersAfter));

        if ($added !== [] || $removed !== []) {
            $changes['members'] = array_filter(['added' => $added, 'removed' => $removed]);
        }

        // A form saved without edits used to leave "Team X: updated" behind it.
        Activity::logChanges('team.updated', Activity::SUBJECT_TEAM, $team->id, $changes, [
            'module' => 'teams',
            'description' => "Updated team {$team->name}: ".implode(', ', array_keys($changes)),
            'properties' => ['team' => $team->slug],
        ]);

        return back()->with('status', 'Team updated.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        if (! $request->user()->hasPermission('teams.manage')) {
            abort(403);
        }

        $name = $team->name;
        $id = $team->id;
        $count = $team->members()->count();
        $team->members()->detach();
        $team->delete();

        Activity::logFor('team.deleted', Activity::SUBJECT_TEAM, $id, [
            'module' => 'teams',
            'description' => "Deleted team \"{$name}\" (had {$count} member(s))",
            'properties' => ['team' => $team->slug, 'members_affected' => $count],
        ]);

        return redirect()->route('teams.index')->with('status', "Team {$name} deleted.");
    }

    private function assignableUsers()
    {
        return User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->with('department:id,name')
            ->get(['id', 'name', 'avatar', 'job_title', 'email', 'department_id'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title', 'email']),
                'department' => $u->departmentName(),
                'initials' => $u->initials,
            ]);
    }
}
