<?php

use App\Modules\TaskManagement\Models\Sprint;
use App\Modules\TaskManagement\Models\SprintSnapshot;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\SprintService;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

function sprints(): SprintService
{
    return app(SprintService::class);
}

function sprintFor($project, array $attributes = []): Sprint
{
    return Sprint::query()->create($attributes + [
        'project_id' => $project->id,
        'name' => 'Sprint '.uniqid(),
        'state' => Sprint::STATE_FUTURE,
    ]);
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

it('creates a sprint in the future state', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->post(route('sprints.store', $project->slug), ['name' => 'Sprint 1', 'goal' => 'Ship checkout'])
        ->assertRedirect();

    $sprint = Sprint::query()->where('name', 'Sprint 1')->firstOrFail();

    expect($sprint->state)->toBe(Sprint::STATE_FUTURE);
    expect($sprint->goal)->toBe('Ship checkout');
});

it('refuses to start an empty sprint', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    $this->actingAs($admin)
        ->post(route('sprints.start', [$project->slug, $sprint->id]))
        ->assertSessionHasErrors('sprint');

    expect($sprint->fresh()->state)->toBe(Sprint::STATE_FUTURE);
});

it('starts a sprint that has work in it', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id]);

    $this->actingAs($admin)->post(route('sprints.start', [$project->slug, $sprint->id]))->assertRedirect();

    $fresh = $sprint->fresh();

    expect($fresh->state)->toBe(Sprint::STATE_ACTIVE);
    expect($fresh->started_at)->not->toBeNull();
    // Day zero is recorded so the burndown has a starting height.
    expect(SprintSnapshot::query()->where('sprint_id', $sprint->id)->count())->toBe(1);
});

it('runs at most one sprint per project', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $running = sprintFor($project, ['state' => Sprint::STATE_ACTIVE]);
    $next = sprintFor($project);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $next->id]);

    $this->actingAs($admin)
        ->post(route('sprints.start', [$project->slug, $next->id]))
        ->assertSessionHasErrors('sprint');

    expect($next->fresh()->state)->toBe(Sprint::STATE_FUTURE);
    expect($running->fresh()->state)->toBe(Sprint::STATE_ACTIVE);
});

it('lets another project run its own sprint at the same time', function () {
    $admin = TaskHelpers::admin();
    $a = TaskHelpers::project($admin);
    $b = TaskHelpers::project($admin);

    sprintFor($a, ['state' => Sprint::STATE_ACTIVE]);
    $mine = sprintFor($b);
    Task::factory()->create(['project_id' => $b->id, 'created_by_id' => $admin->id, 'sprint_id' => $mine->id]);

    $this->actingAs($admin)->post(route('sprints.start', [$b->slug, $mine->id]))->assertRedirect();

    expect($mine->fresh()->state)->toBe(Sprint::STATE_ACTIVE);
});

it('refuses to restart a completed sprint', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project, ['state' => Sprint::STATE_COMPLETED]);

    $this->actingAs($admin)
        ->post(route('sprints.start', [$project->slug, $sprint->id]))
        ->assertSessionHasErrors('sprint');
});

/*
|--------------------------------------------------------------------------
| Completing a sprint
|--------------------------------------------------------------------------
*/

it('sends unfinished work back to the backlog on completion', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project, ['state' => Sprint::STATE_ACTIVE]);

    $done = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'sprint_id' => $sprint->id, 'status' => 'done',
    ]);
    $open = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'sprint_id' => $sprint->id, 'status' => 'in_progress',
    ]);

    $this->actingAs($admin)
        ->post(route('sprints.complete', [$project->slug, $sprint->id]), ['move_to' => 'backlog'])
        ->assertRedirect();

    expect($sprint->fresh()->state)->toBe(Sprint::STATE_COMPLETED);
    // Finished work stays with the sprint it was finished in.
    expect($done->fresh()->sprint_id)->toBe($sprint->id);
    expect($open->fresh()->sprint_id)->toBeNull();
});

it('carries unfinished work into the next sprint when asked', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project, ['state' => Sprint::STATE_ACTIVE, 'position' => 1]);
    $next = sprintFor($project, ['position' => 2]);

    $open = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'sprint_id' => $sprint->id, 'status' => 'todo',
    ]);

    $this->actingAs($admin)
        ->post(route('sprints.complete', [$project->slug, $sprint->id]), ['move_to' => 'next'])
        ->assertRedirect();

    expect($open->fresh()->sprint_id)->toBe($next->id);
});

it('refuses to complete a sprint that is not running', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    $this->actingAs($admin)
        ->post(route('sprints.complete', [$project->slug, $sprint->id]), ['move_to' => 'backlog'])
        ->assertSessionHasErrors('sprint');
});

it('keeps the work when a sprint is deleted', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id]);

    $this->actingAs($admin)->delete(route('sprints.destroy', [$project->slug, $sprint->id]))->assertRedirect();

    expect(Sprint::query()->whereKey($sprint->id)->exists())->toBeFalse();
    expect(Task::query()->whereKey($task->id)->exists())->toBeTrue();
    expect($task->fresh()->sprint_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Backlog management
|--------------------------------------------------------------------------
*/

it('moves selected work into a sprint and back out', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    $a = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    $b = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('sprints.assign', $project->slug), ['task_ids' => [$a->id, $b->id], 'sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($a->fresh()->sprint_id)->toBe($sprint->id);

    $this->actingAs($admin)
        ->post(route('sprints.assign', $project->slug), ['task_ids' => [$a->id], 'sprint_id' => null])
        ->assertRedirect();

    expect($a->fresh()->sprint_id)->toBeNull();
    expect($b->fresh()->sprint_id)->toBe($sprint->id);
});

it('never pulls another project task into a sprint', function () {
    $admin = TaskHelpers::admin();
    $mine = TaskHelpers::project($admin);
    $other = TaskHelpers::project($admin);

    $sprint = sprintFor($mine);
    $foreign = Task::factory()->create(['project_id' => $other->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('sprints.assign', $mine->slug), ['task_ids' => [$foreign->id], 'sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($foreign->fresh()->sprint_id)->toBeNull();
});

it('refuses a sprint belonging to another project', function () {
    $admin = TaskHelpers::admin();
    $mine = TaskHelpers::project($admin);
    $other = TaskHelpers::project($admin);
    $foreignSprint = sprintFor($other);

    $task = Task::factory()->create(['project_id' => $mine->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('sprints.assign', $mine->slug), ['task_ids' => [$task->id], 'sprint_id' => $foreignSprint->id])
        ->assertSessionHasErrors('sprint');
});

it('sets a story point estimate', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('sprints.estimate', $project->slug), ['task_ids' => [$task->id], 'story_points' => 3.5])
        ->assertRedirect();

    expect($task->fresh()->story_points)->toBe(3.5);
});

it('clears an estimate when no value is sent', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'story_points' => 5]);

    $this->actingAs($admin)
        ->post(route('sprints.estimate', $project->slug), ['task_ids' => [$task->id], 'story_points' => null])
        ->assertRedirect();

    expect($task->fresh()->story_points)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Metrics
|--------------------------------------------------------------------------
*/

it('totals points and counts, splitting done from open', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id, 'status' => 'done', 'story_points' => 3]);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id, 'status' => 'todo', 'story_points' => 5]);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id, 'status' => 'todo', 'story_points' => null]);

    $totals = sprints()->totals($sprint);

    expect($totals['total_points'])->toBe(8.0);
    expect($totals['completed_points'])->toBe(3.0);
    expect($totals['total_tasks'])->toBe(3);
    expect($totals['completed_tasks'])->toBe(1);
});

it('overwrites rather than duplicates a snapshot taken twice in a day', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project, ['state' => Sprint::STATE_ACTIVE]);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id, 'story_points' => 5]);

    sprints()->snapshot($sprint);
    sprints()->snapshot($sprint);

    expect(SprintSnapshot::query()->where('sprint_id', $sprint->id)->count())->toBe(1);
    expect(SprintSnapshot::query()->where('sprint_id', $sprint->id)->value('remaining_points'))->toEqual(5.0);
});

it('snapshots every running sprint and skips the others', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $running = sprintFor($project, ['state' => Sprint::STATE_ACTIVE]);
    $future = sprintFor($project);

    expect(sprints()->snapshotAllActive())->toBe(1);
    expect(SprintSnapshot::query()->where('sprint_id', $running->id)->count())->toBe(1);
    expect(SprintSnapshot::query()->where('sprint_id', $future->id)->count())->toBe(0);
});

it('builds a burndown with an ideal line running to zero', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $sprint = sprintFor($project, [
        'state' => Sprint::STATE_ACTIVE,
        'starts_at' => now()->subDays(4)->toDateString(),
        'ends_at' => now()->toDateString(),
    ]);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id, 'story_points' => 8]);

    sprints()->snapshot($sprint, now()->subDays(4));
    $series = sprints()->burndown($sprint->fresh('snapshots'));

    expect($series)->toHaveCount(5);
    expect($series[0]['ideal'])->toEqual(8.0);
    expect(end($series)['ideal'])->toEqual(0.0);
    expect($series[0]['remaining'])->toEqual(8.0);
});

it('reports velocity from completed sprints only', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $done = sprintFor($project, ['state' => Sprint::STATE_COMPLETED, 'completed_at' => now()->subDay(), 'name' => 'Sprint A']);
    sprintFor($project, ['state' => Sprint::STATE_ACTIVE, 'name' => 'Sprint B']);

    SprintSnapshot::query()->create([
        'sprint_id' => $done->id,
        'snapshot_date' => now()->subDays(5)->toDateString(),
        'remaining_points' => 10, 'completed_points' => 0,
        'remaining_tasks' => 4, 'completed_tasks' => 0,
    ]);
    SprintSnapshot::query()->create([
        'sprint_id' => $done->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'remaining_points' => 2, 'completed_points' => 8,
        'remaining_tasks' => 1, 'completed_tasks' => 3,
    ]);

    $velocity = sprints()->velocity($project);

    expect($velocity)->toHaveCount(1);
    expect($velocity[0]['sprint'])->toBe('Sprint A');
    // Committed is day-one scope, not what the sprint ended up holding.
    expect($velocity[0]['committed'])->toEqual(10.0);
    expect($velocity[0]['completed'])->toEqual(8.0);
});

/*
|--------------------------------------------------------------------------
| Screens and access
|--------------------------------------------------------------------------
*/

it('shows the backlog with sprints and unassigned work separated', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $sprint = sprintFor($project);

    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'sprint_id' => $sprint->id]);
    Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('sprints.backlog', $project->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sprints', 1)->has('tasks', 2)->where('can.manage', true));
});

it('hides completed sprints from the backlog', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    sprintFor($project, ['state' => Sprint::STATE_COMPLETED]);
    sprintFor($project);

    $this->actingAs($admin)
        ->get(route('sprints.backlog', $project->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sprints', 1));
});

it('renders the sprint report with no sprint running', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $this->actingAs($admin)
        ->get(route('sprints.report', $project->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('active', null)->has('velocity', 0));
});

it('only shows a member the work they may see', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['projects.view', 'tasks.view']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$member->id => ['role' => 'member']]);

    // Sitting on the project's member list is not a licence to read the whole
    // backlog: without `tasks.view-all` a member sees the work that is theirs.
    Task::factory()->create([
        'project_id' => $project->id,
        'created_by_id' => $owner->id,
        'assignee_id' => $member->id,
    ]);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'created_by_id' => $owner->id]);

    $this->actingAs($member)
        ->get(route('sprints.backlog', $project->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tasks', 1)->where('can.manage', false));
});

it('refuses backlog changes to someone who cannot edit the project', function () {
    $owner = TaskHelpers::admin();
    $member = TaskHelpers::userWith(['projects.view', 'tasks.view']);
    $project = TaskHelpers::project($owner);
    $project->members()->attach([$member->id => ['role' => 'member']]);

    $this->actingAs($member)
        ->post(route('sprints.store', $project->slug), ['name' => 'Sneaky sprint'])
        ->assertForbidden();

    expect(Sprint::query()->where('name', 'Sneaky sprint')->exists())->toBeFalse();
});
