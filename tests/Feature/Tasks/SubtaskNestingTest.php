<?php

use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Services\SubtaskRollupService;
use Tests\Feature\Tasks\TaskHelpers;

beforeEach(function () {
    TaskHelpers::bootstrap();
});

function rollup(): SubtaskRollupService
{
    return app(SubtaskRollupService::class);
}

/** Creates a child under $parent through the endpoint. */
function raiseChild($test, $admin, $project, ?Task $parent, string $title)
{
    $test->actingAs($admin)->post(route('tasks.store'), array_filter([
        'project_id' => $project->id,
        'title' => $title,
        'priority' => 'medium',
        'parent_task_id' => $parent?->id,
    ]));

    return Task::query()->where('title', $title)->first();
}

/*
|--------------------------------------------------------------------------
| Nesting
|--------------------------------------------------------------------------
*/

it('allows a sub-task under a sub-task', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = raiseChild($this, $admin, $project, null, 'Root work');
    $child = raiseChild($this, $admin, $project, $root, 'Child work');
    $grandchild = raiseChild($this, $admin, $project, $child, 'Grandchild work');

    expect($grandchild)->not->toBeNull();
    expect($grandchild->parent_task_id)->toBe($child->id);
});

it('refuses to nest deeper than the cap', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = raiseChild($this, $admin, $project, null, 'L0');
    $child = raiseChild($this, $admin, $project, $root, 'L1');
    $grandchild = raiseChild($this, $admin, $project, $child, 'L2');

    $this->actingAs($admin)->post(route('tasks.store'), [
        'project_id' => $project->id,
        'title' => 'L3 too deep',
        'priority' => 'medium',
        'parent_task_id' => $grandchild->id,
    ])->assertSessionHasErrors('parent_task_id');

    expect(Task::query()->where('title', 'L3 too deep')->exists())->toBeFalse();
});

it('still refuses a parent in another project', function () {
    $admin = TaskHelpers::admin();
    $mine = TaskHelpers::project($admin);
    $other = TaskHelpers::project($admin);

    $foreign = Task::factory()->create(['project_id' => $other->id, 'created_by_id' => $admin->id]);

    $this->actingAs($admin)->post(route('tasks.store'), [
        'project_id' => $mine->id,
        'title' => 'Wrong project',
        'priority' => 'medium',
        'parent_task_id' => $foreign->id,
    ])->assertSessionHasErrors('parent_task_id');
});

it('reports how deep a task sits', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    $child = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'parent_task_id' => $root->id]);
    $grandchild = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'parent_task_id' => $child->id]);

    expect(rollup()->depthOf($root))->toBe(0);
    expect(rollup()->depthOf($child))->toBe(1);
    expect(rollup()->depthOf($grandchild))->toBe(2);
});

it('refuses to make a task its own descendant', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    $child = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id, 'parent_task_id' => $root->id]);

    // Moving the root under its own child would make a cycle.
    expect(rollup()->canNest($root, $child))->toBeFalse();
    expect(rollup()->canNest($root, $root))->toBeFalse();
    expect(rollup()->canNest($child, null))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Roll-up
|--------------------------------------------------------------------------
*/

it('counts the whole subtree, not just direct children', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    $child = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'parent_task_id' => $root->id, 'status' => 'done',
    ]);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'parent_task_id' => $child->id, 'status' => 'todo',
    ]);

    $progress = rollup()->progressOf($root);

    expect($progress['total'])->toBe(2);
    expect($progress['done'])->toBe(1);
    expect($progress['percent'])->toBe(50);
});

it('reports nothing for a task with no children', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    expect(rollup()->progressOf($task))->toBe(['total' => 0, 'done' => 0, 'percent' => 0]);
});

it('does not claim all children are done when there are none', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);
    $task = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);

    expect(rollup()->allChildrenDone($task))->toBeFalse();
});

it('knows when a parent still has open work beneath it', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    $child = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'parent_task_id' => $root->id, 'status' => 'todo',
    ]);

    expect(rollup()->hasOpenChildren($root))->toBeTrue();

    $child->forceFill(['status' => 'done'])->save();

    expect(rollup()->hasOpenChildren($root->fresh()))->toBeFalse();
});

it('exposes the roll-up on the task page', function () {
    $admin = TaskHelpers::admin();
    $project = TaskHelpers::project($admin);

    $root = Task::factory()->create(['project_id' => $project->id, 'created_by_id' => $admin->id]);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $admin->id,
        'parent_task_id' => $root->id, 'status' => 'done',
    ]);

    $this->actingAs($admin)
        ->get(route('tasks.show', $root))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subtaskProgress.total', 1)
            ->where('subtaskProgress.percent', 100));
});

/*
|--------------------------------------------------------------------------
| The nudge
|--------------------------------------------------------------------------
*/

it('tells the parent owner when the last sub-task lands', function () {
    $owner = TaskHelpers::admin();
    $parentOwner = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $parent = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'assignee_id' => $parentOwner->id, 'status' => 'todo',
    ]);
    $child = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'parent_task_id' => $parent->id, 'status' => 'todo',
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $child), ['status' => 'done']);

    expect(Notification::query()
        ->where('user_id', $parentOwner->id)
        ->where('type', 'task.subtasks-complete')
        ->count())->toBe(1);
});

it('stays quiet while any sub-task is still open', function () {
    $owner = TaskHelpers::admin();
    $parentOwner = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $parent = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'assignee_id' => $parentOwner->id,
    ]);
    $first = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'parent_task_id' => $parent->id, 'status' => 'todo',
    ]);
    Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'parent_task_id' => $parent->id, 'status' => 'todo',
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $first), ['status' => 'done']);

    expect(Notification::query()->where('type', 'task.subtasks-complete')->count())->toBe(0);
});

it('stays quiet when the parent is already finished', function () {
    $owner = TaskHelpers::admin();
    $parentOwner = TaskHelpers::userWith(['tasks.view']);
    $project = TaskHelpers::project($owner);

    $parent = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'assignee_id' => $parentOwner->id, 'status' => 'done', 'completed_at' => now(),
    ]);
    $child = Task::factory()->create([
        'project_id' => $project->id, 'created_by_id' => $owner->id,
        'parent_task_id' => $parent->id, 'status' => 'todo',
    ]);

    $this->actingAs($owner)->patch(route('tasks.status', $child), ['status' => 'done']);

    expect(Notification::query()->where('type', 'task.subtasks-complete')->count())->toBe(0);
});
