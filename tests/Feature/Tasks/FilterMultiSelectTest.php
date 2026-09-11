<?php

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\SavedFilter;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\UserManagement\Models\Activity;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

/*
|--------------------------------------------------------------------------
| Filters accept several values at once
|--------------------------------------------------------------------------
|
| The filter controls are multi-select, so every filtered list has to treat a
| value as a set. A single value must keep working so existing links and saved
| filters are unaffected.
|
*/

it('filters tasks by several statuses at once', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo']);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'in_progress']);
    Task::factory()->count(4)->create(['project_id' => $project->id, 'status' => 'done', 'completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => ['todo', 'in_progress'], 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 5));
});

it('still accepts a single task status', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'todo']);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'in_progress']);

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => 'todo', 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 3));
});

it('filters tasks by several projects at once', function () {
    $user = TaskHelpers::admin();
    $a = TaskHelpers::project($user);
    $b = TaskHelpers::project($user);
    $c = TaskHelpers::project($user);

    Task::factory()->count(2)->create(['project_id' => $a->id]);
    Task::factory()->count(3)->create(['project_id' => $b->id]);
    Task::factory()->count(4)->create(['project_id' => $c->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['project' => [$a->slug, $b->slug], 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 5));
});

it('filters tasks by several priorities and assignees at once', function () {
    $user = TaskHelpers::admin();
    $alice = TaskHelpers::userWith(['tasks.view']);
    $bob = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($user);

    Task::factory()->create(['project_id' => $project->id, 'priority' => 'critical', 'assignee_id' => $alice->id]);
    Task::factory()->create(['project_id' => $project->id, 'priority' => 'high', 'assignee_id' => $bob->id]);
    Task::factory()->create(['project_id' => $project->id, 'priority' => 'low', 'assignee_id' => $alice->id]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['priority' => ['critical', 'high'], 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 2));

    $this->actingAs($user)
        ->get(route('tasks.index', ['assignee' => [$alice->id, $bob->id], 'view' => 'list']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 3));
});

it('combines multi-value filters with AND across fields', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    $other = TaskHelpers::project($user);

    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'priority' => 'high']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress', 'priority' => 'low']);
    Task::factory()->create(['project_id' => $other->id, 'status' => 'todo', 'priority' => 'high']);

    // status IN (todo, in_progress) AND project IN (project)
    $this->actingAs($user)
        ->get(route('tasks.index', [
            'status' => ['todo', 'in_progress'],
            'project' => [$project->slug],
            'view' => 'list',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 2));
});

it('carries multi-value filters through to the CSV export', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);

    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo', 'title' => 'Keep me']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress', 'title' => 'Keep me too']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'done', 'title' => 'Exclude me', 'completed_at' => now()]);

    $body = $this->actingAs($user)
        ->get(route('tasks.export', ['status' => ['todo', 'in_progress']]))
        ->assertOk()
        ->streamedContent();

    expect($body)->toContain('Keep me');
    expect($body)->toContain('Keep me too');
    expect($body)->not->toContain('Exclude me');
});

it('persists a multi-value filter in a saved filter', function () {
    $user = TaskHelpers::admin();
    $project = TaskHelpers::project($user);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => 'todo']);

    $this->actingAs($user)->post(route('task-filters.store'), [
        'name' => 'Open work',
        'query' => ['status' => ['todo', 'in_progress'], 'priority' => ['high']],
    ])->assertRedirect();

    $saved = SavedFilter::query()->where('name', 'Open work')->firstOrFail();

    expect($saved->query['status'])->toBe(['todo', 'in_progress']);
    expect($saved->query['priority'])->toBe(['high']);
});

/*
|--------------------------------------------------------------------------
| The same applies to the other filtered lists
|--------------------------------------------------------------------------
*/

it('filters projects by several statuses at once', function () {
    $user = TaskHelpers::admin();

    Project::factory()->create(['owner_id' => $user->id, 'status' => 'active']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'planning']);
    Project::factory()->create(['owner_id' => $user->id, 'status' => 'completed']);

    $this->actingAs($user)
        ->get(route('projects.index', ['status' => ['active', 'planning']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 2));
});

it('filters users by several roles and statuses at once', function () {
    $admin = TaskHelpers::admin();
    TaskHelpers::userWith(['tasks.view'], 'alpha');
    TaskHelpers::userWith(['tasks.view'], 'beta');

    User::factory()->create(['status' => 'suspended']);
    User::factory()->create(['status' => 'invited']);

    $this->actingAs($admin)
        ->get(route('users.index', ['status' => ['suspended', 'invited']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 2));
});

it('filters the activity log by several modules at once', function () {
    $user = TaskHelpers::admin();

    foreach (['tasks', 'projects', 'reports'] as $module) {
        Activity::query()->create([
            'user_id' => $user->id,
            'action' => "{$module}.created",
            'module' => $module,
            'description' => "Something in {$module}",
        ]);
    }

    $this->actingAs($user)
        ->get(route('activity.index', ['module' => ['tasks', 'projects']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activities.data', 2));
});
