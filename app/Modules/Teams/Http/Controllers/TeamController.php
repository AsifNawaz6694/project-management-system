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
        $team->load(['lead:id,name,avatar,job_title', 'members:id,name,avatar,job_title,department,email']);

        $activities = Activity::query()
            ->where('module', 'teams')
            ->whereJsonContains('properties->team_id', $team->id)
            ->latest()
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

        Activity::log('team.created', [
            'module' => 'teams',
            'description' => "Created team {$team->name} with ".count($memberIds).' member(s)',
            'properties' => ['team_id' => $team->id, 'team' => $team->slug, 'members' => $memberIds],
        ]);

        return redirect()->route('teams.show', $team)->with('status', "Team {$team->name} created.");
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $original = ['name' => $team->name, 'lead_id' => $team->lead_id];

        $team->fill($request->only(['name', 'description', 'color', 'lead_id']))->save();

        if ($request->has('member_ids')) {
            $memberIds = collect($request->input('member_ids', []))
                ->push($team->lead_id)
                ->filter()->unique()->values()->all();
            $sync = [];
            foreach ($memberIds as $id) {
                $sync[(int) $id] = ['role' => $id == $team->lead_id ? 'lead' : 'member'];
            }
            $team->members()->sync($sync);
        }

        $changes = [];
        if ($original['name'] !== $team->name) $changes[] = "renamed to {$team->name}";
        if ($original['lead_id'] !== $team->lead_id) $changes[] = "lead changed";
        $summary = empty($changes) ? 'updated' : implode(', ', $changes);

        Activity::log('team.updated', [
            'module' => 'teams',
            'description' => "Team {$team->name}: {$summary}",
            'properties' => ['team_id' => $team->id, 'team' => $team->slug, 'changes' => $changes],
        ]);

        return back()->with('status', 'Team updated.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        if (! $request->user()->hasPermission('teams.manage')) {
            abort(403);
        }

        $name = $team->name;
        $count = $team->members()->count();
        $team->members()->detach();
        $team->delete();

        Activity::log('team.deleted', [
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
            ->get(['id', 'name', 'avatar', 'job_title', 'email', 'department'])
            ->map(fn (User $u) => [
                ...$u->only(['id', 'name', 'avatar', 'job_title', 'email', 'department']),
                'initials' => $u->initials,
            ]);
    }
}
