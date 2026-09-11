<?php

use App\Modules\TaskManagement\Models\Task;
use App\Modules\Teams\Models\Team;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| S1 / F3 — writes must respect the same visibility as reads
|--------------------------------------------------------------------------
*/

it('forbids updating a task outside the user visibility scope', function () {
    $stranger = TaskHelpers::userWith(['tasks.view', 'tasks.update']);
    $owner = TaskHelpers::admin();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    // The stranger holds tasks.update globally but is not on this project.
    $this->actingAs($stranger)
        ->patch(route('tasks.update', $task), ['title' => 'Hijacked'])
        ->assertForbidden();

    expect($task->fresh()->title)->not->toBe('Hijacked');
});

it('forbids deleting a task outside the user visibility scope', function () {
    $stranger = TaskHelpers::userWith(['tasks.view', 'tasks.delete']);
    $owner = TaskHelpers::admin();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($stranger)
        ->delete(route('tasks.destroy', $task))
        ->assertForbidden();

    expect(Task::query()->find($task->id))->not->toBeNull();
});

it('forbids changing status on a task outside the visibility scope', function () {
    $stranger = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);
    $owner = TaskHelpers::admin();

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($stranger)
        ->patch(route('tasks.status', $task), ['status' => 'done'])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe('todo');
});

it('allows the assignee to move status but not to edit other fields', function () {
    $owner = TaskHelpers::admin();
    $assignee = TaskHelpers::userWith(['tasks.view', 'tasks.update-status']);

    $project = TaskHelpers::project($owner);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->patch(route('tasks.status', $task), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('in_progress');

    // No tasks.update, so a full edit is refused.
    $this->actingAs($assignee)
        ->patch(route('tasks.update', $task), ['title' => 'Renamed by assignee'])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| S3 — foreign keys must be scoped
|--------------------------------------------------------------------------
*/

it('refuses to create a task in a project the user cannot see', function () {
    $creator = TaskHelpers::userWith(['tasks.view', 'tasks.create']);
    $other = TaskHelpers::admin();
    $foreignProject = TaskHelpers::project($other);

    $this->actingAs($creator)
        ->post(route('tasks.store'), [
            'project_id' => $foreignProject->id,
            'title' => 'Planted task',
            'priority' => 'medium',
        ])
        ->assertSessionHasErrors('project_id');

    expect(Task::query()->where('title', 'Planted task')->exists())->toBeFalse();
});

it('refuses a parent in another project, and nesting past the depth cap', function () {
    $user = TaskHelpers::admin();
    $projectA = TaskHelpers::project($user);
    $projectB = TaskHelpers::project($user);

    $parent = Task::factory()->create(['project_id' => $projectA->id]);
    $subtask = Task::factory()->create(['project_id' => $projectA->id, 'parent_task_id' => $parent->id]);

    // Cross-project parent.
    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $projectB->id,
        'title' => 'Cross project child',
        'priority' => 'medium',
        'parent_task_id' => $parent->id,
    ])->assertSessionHasErrors('parent_task_id');

    // Nesting is allowed now, but bounded — a grandchild is fine...
    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $projectA->id,
        'title' => 'Grandchild',
        'priority' => 'medium',
        'parent_task_id' => $subtask->id,
    ])->assertRedirect();

    $grandchild = Task::query()->where('title', 'Grandchild')->firstOrFail();

    // ...and a fourth level is not.
    $this->actingAs($user)->post(route('tasks.store'), [
        'project_id' => $projectA->id,
        'title' => 'Great-grandchild',
        'priority' => 'medium',
        'parent_task_id' => $grandchild->id,
    ])->assertSessionHasErrors('parent_task_id');
});

/*
|--------------------------------------------------------------------------
| S4 — a write permission must not grant global read
|--------------------------------------------------------------------------
*/

it('does not let tasks.create grant visibility of every task', function () {
    $creator = TaskHelpers::userWith(['tasks.view', 'tasks.create']);
    $other = TaskHelpers::admin();

    $foreignProject = TaskHelpers::project($other);
    Task::factory()->count(4)->create(['project_id' => $foreignProject->id]);

    $this->actingAs($creator)
        ->get(route('tasks.index', ['view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 0));
});

it('grants workspace-wide visibility only with tasks.view-all', function () {
    $viewer = TaskHelpers::userWith(['tasks.view', 'tasks.view-all']);
    $other = TaskHelpers::admin();

    $foreignProject = TaskHelpers::project($other);
    Task::factory()->count(4)->create(['project_id' => $foreignProject->id]);

    $this->actingAs($viewer)
        ->get(route('tasks.index', ['view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 4));
});

it('lets a team member see work routed to their team', function () {
    $member = TaskHelpers::userWith(['tasks.view']);
    $other = TaskHelpers::admin();

    $team = Team::query()->create(['name' => 'Platform', 'color' => 'blue']);
    $team->members()->attach($member->id);

    $foreignProject = TaskHelpers::project($other);
    Task::factory()->create(['project_id' => $foreignProject->id, 'team_id' => $team->id]);
    Task::factory()->create(['project_id' => $foreignProject->id]);

    $this->actingAs($member->fresh())
        ->get(route('tasks.index', ['view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1));
});

/*
|--------------------------------------------------------------------------
| Export must be gated
|--------------------------------------------------------------------------
*/

it('blocks CSV export without the export permission', function () {
    $user = TaskHelpers::userWith(['tasks.view']);

    $this->actingAs($user)->get(route('tasks.export'))->assertForbidden();
});

it('streams a CSV export for a permitted user', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Exportable task']);

    $response = $this->actingAs($user)->get(route('tasks.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();
    expect($body)->toContain('Exportable task');
    expect($body)->toContain('Key,Title,Type,Status');
});
