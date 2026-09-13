<?php

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Tests\Feature\Tasks\TaskHelpers;

/**
 * What an employee — a developer with no `*.view-all` — may see.
 *
 * Three rules, all of which used to be broken in a different way:
 *  - tasks are theirs (assigned, raised, or routed to their team), not their
 *    whole project's;
 *  - projects are the ones they are working in;
 *  - the reports dashboard answers those same two questions rather than the
 *    workspace-wide one.
 */
beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Tasks
|--------------------------------------------------------------------------
*/

it('does not show a project member a colleague\'s task', function () {
    $owner = TaskHelpers::admin();
    $employee = TaskHelpers::userWith(['projects.view', 'tasks.view']);

    $project = TaskHelpers::project($owner);
    $project->members()->attach([$employee->id => ['role' => 'member']]);

    $mine = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $employee->id,
        'title' => 'My ticket',
    ]);
    $theirs = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'title' => 'Somebody else\'s ticket',
    ]);

    $this->actingAs($employee)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1)->where('tasks.0.id', $mine->id));

    // And the row itself is out of reach, not merely off the list.
    $this->actingAs($employee)->get(route('tasks.show', $theirs))->assertForbidden();
});

it('still shows an employee work routed to their team', function () {
    $owner = TaskHelpers::admin();
    $employee = TaskHelpers::userWith(['projects.view', 'tasks.view']);

    $team = Team::query()->create(['name' => 'Backend', 'slug' => 'backend-'.uniqid()]);
    $team->members()->attach($employee->id, ['role' => 'member']);

    $project = TaskHelpers::project($owner);

    // Unassigned, but pointed at their team — this is work waiting to be picked
    // up, so it has to stay visible.
    Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'team_id' => $team->id,
    ]);

    $this->actingAs($employee)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1));
});

/*
|--------------------------------------------------------------------------
| Projects
|--------------------------------------------------------------------------
*/

it('lists only the projects an employee is working in', function () {
    $owner = TaskHelpers::admin();
    $employee = TaskHelpers::userWith(['projects.view', 'tasks.view']);

    $mine = TaskHelpers::project($owner);
    $mine->members()->attach([$employee->id => ['role' => 'member']]);

    $theirs = TaskHelpers::project($owner);

    $this->actingAs($employee)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $mine->id)
            ->where('stats.total', 1));

    $this->actingAs($employee)->get(route('projects.show', $theirs->slug))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Teams
|--------------------------------------------------------------------------
*/

it('keeps the teams panel away from the employee role', function () {
    (new RolePermissionSeeder)->run();

    $employee = User::factory()->create(['status' => 'active']);
    $employee->roles()->attach(
        Role::query()->where('slug', 'employee')->value('id')
    );

    expect($employee->fresh(['roles.permissions'])->hasPermission('teams.view'))->toBeFalse();

    $this->actingAs($employee->fresh())->get(route('teams.index'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
*/

it('scopes the reports dashboard to the viewer without reports.view-all', function () {
    $owner = TaskHelpers::admin();
    $employee = TaskHelpers::userWith(['projects.view', 'tasks.view', 'reports.view']);

    $mine = TaskHelpers::project($owner);
    $mine->members()->attach([$employee->id => ['role' => 'member']]);
    TaskHelpers::project($owner);

    Task::factory()->create([
        'project_id' => $mine->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $employee->id,
    ]);
    Task::factory()->count(3)->create(['project_id' => $mine->id, 'created_by_id' => $owner->id]);

    $this->actingAs($employee)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scoped', true)
            ->where('overview.tasks_total', 1)
            ->where('overview.projects_total', 1)
            // Ranking colleagues is a workspace-wide surface only.
            ->has('workloadByUser', 0)
            ->has('workloadByTeam', 0)
            ->has('topPerformers', 0));
});

it('leaves the reports dashboard workspace-wide for reports.view-all', function () {
    $owner = TaskHelpers::admin();
    $manager = TaskHelpers::userWith([
        'projects.view', 'projects.view-all', 'tasks.view', 'tasks.view-all',
        'reports.view', 'reports.view-all',
    ]);

    $project = TaskHelpers::project($owner);
    Task::factory()->count(4)->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scoped', false)
            ->where('overview.tasks_total', 4)
            ->where('overview.projects_total', Project::query()->count()));
});
